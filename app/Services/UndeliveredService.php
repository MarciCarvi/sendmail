<?php

namespace App\Services;

use App\Models\CampaignSend;
use App\Models\Setting;
use App\Models\SesEvent;
use App\Models\Undelivered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UndeliveredService
{
    /** Refusi evidenti di domini molto comuni. */
    private const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmil.com' => 'gmail.com',
        'gnail.com' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.cm' => 'gmail.com',
        'gmail.con' => 'gmail.com', 'gmail.om' => 'gmail.com',
        'hotmial.com' => 'hotmail.com', 'hotmal.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'hotnail.com' => 'hotmail.com', 'hotmil.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'oulook.com' => 'outlook.com', 'outlook.con' => 'outlook.com',
        'outlook.co' => 'outlook.com',
        'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com', 'yhaoo.com' => 'yahoo.com',
        'yahho.com' => 'yahoo.com', 'yahoo.con' => 'yahoo.com', 'yahoo.co' => 'yahoo.com',
        'liberoo.it' => 'libero.it', 'libero.i' => 'libero.it', 'libero.itt' => 'libero.it',
        'tiscali.i' => 'tiscali.it', 'virgilio.i' => 'virgilio.it', 'virgillio.it' => 'virgilio.it',
    ];

    /** Estensioni sbagliate (.con, .cmo…) → .com */
    private const TLD_TYPOS = ['con' => 'com', 'cmo' => 'com', 'ocm' => 'com', 'vom' => 'com', 'comm' => 'com', 'itt' => 'it'];

    public function threshold(): int
    {
        return max(1, (int) Setting::get('undelivered_threshold', 3));
    }

    public function graceHours(): int
    {
        return max(0, (int) Setting::get('undelivered_grace_hours', 48));
    }

    /**
     * Da quando i dati di consegna sono affidabili: il registro eventi (1.5.1) è la fonte, quindi
     * si considerano solo gli invii successivi al primo evento registrato.
     */
    public function reliableSince(): ?Carbon
    {
        $first = SesEvent::min('created_at');

        return $first ? Carbon::parse($first)->subHour() : null;
    }

    /**
     * Cerca gli indirizzi con gli ultimi N invii (valutabili) tutti senza consegna o con bounce
     * e li segnala. Restituisce quanti nuovi indirizzi sono stati segnalati.
     */
    public function evaluate(): int
    {
        $threshold = $this->threshold();
        $since = $this->reliableSince();
        if (!$since) {
            return 0;
        }

        $cutoff = now()->subHours($this->graceHours());

        // Solo invii accettati da SES, abbastanza vecchi perché gli eventi siano arrivati
        $base = fn() => DB::table('sm_campaign_sends as cs')
            ->join('sm_subscribers as s', 's.id', '=', 'cs.subscriber_id')
            ->where('cs.status', 'sent')
            ->whereNotNull('cs.sent_at')
            ->where('cs.sent_at', '>=', $since)
            ->where('cs.sent_at', '<=', $cutoff);

        $candidates = $base()->groupBy('s.email')->havingRaw('COUNT(*) >= ?', [$threshold])->pluck('s.email');

        $flagged = 0;

        foreach ($candidates->chunk(500) as $chunk) {
            $existing = Undelivered::whereIn('email', $chunk->map(fn($e) => strtolower($e))->all())
                ->get()->keyBy(fn($u) => strtolower($u->email));

            $byEmail = $base()
                ->whereIn('s.email', $chunk->all())
                ->orderBy('s.email')->orderByDesc('cs.sent_at')->orderByDesc('cs.id')
                ->get(['s.email', 'cs.id', 'cs.campaign_id', 'cs.sent_at', 'cs.delivered_at',
                       'cs.bounced_at', 'cs.bounce_type', 'cs.bounce_subtype'])
                ->groupBy(fn($r) => strtolower($r->email));

            foreach ($byEmail as $email => $rows) {
                $current = $existing->get($email);

                if ($current && !$current->cleared_at) {
                    continue; // già segnalato
                }

                // dopo una riabilitazione conta solo quello che succede dopo
                if ($current?->cleared_at) {
                    $rows = $rows->filter(fn($r) => Carbon::parse($r->sent_at)->gt($current->cleared_at));
                }

                $last = $rows->take($threshold)->values();
                if ($last->count() < $threshold || !$last->every(fn($r) => $this->isFailure($r))) {
                    continue;
                }

                $domain = $this->domainOf($email);
                Undelivered::updateOrCreate(['email' => $email], [
                    'domain'           => $domain,
                    'consecutive'      => $threshold,
                    'last_campaign_id' => $last->first()->campaign_id,
                    'evidence'         => $last->map(fn($r) => [
                        'campaign_id' => $r->campaign_id,
                        'sent_at'     => (string) $r->sent_at,
                        'reason'      => $r->bounced_at
                            ? 'Bounce ' . trim(($r->bounce_type ?? '') . '/' . ($r->bounce_subtype ?? ''), '/')
                            : 'Nessun evento di consegna',
                    ])->all(),
                    'suggestion'       => $this->suggestDomain($domain),
                    'flagged_at'       => now(),
                    'cleared_at'       => null,
                ]);
                $flagged++;
            }
        }

        return $flagged;
    }

    /** Un invio è "non consegnato" se manca la consegna oppure è arrivato un bounce. */
    private function isFailure(object $row): bool
    {
        return $row->delivered_at === null || $row->bounced_at !== null;
    }

    public function domainOf(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : strtolower(substr($email, $at + 1));
    }

    public function suggestDomain(string $domain): ?string
    {
        if (isset(self::TYPOS[$domain])) {
            return self::TYPOS[$domain];
        }

        $dot = strrpos($domain, '.');
        if ($dot !== false) {
            $tld = substr($domain, $dot + 1);
            if (isset(self::TLD_TYPOS[$tld])) {
                return substr($domain, 0, $dot + 1) . self::TLD_TYPOS[$tld];
            }
        }

        return null;
    }

    /**
     * Controllo DNS del dominio: ok (ha record MX), no_mx (nessun MX ma risolve), null_mx (dichiara di non
     * ricevere posta), dead (non risolve), unknown (controllo non disponibile o fallito).
     */
    public function checkDomain(string $domain): string
    {
        if ($domain === '' || !function_exists('dns_get_record')) {
            return 'unknown';
        }

        try {
            $mx = @dns_get_record($domain, DNS_MX);
            if ($mx === false) {
                return 'unknown';
            }

            if (!empty($mx)) {
                $targets = array_filter(array_map(fn($r) => rtrim($r['target'] ?? '', '.'), $mx));

                return empty($targets) ? 'null_mx' : 'ok';
            }

            $addr = @dns_get_record($domain, DNS_A + DNS_AAAA);
            if ($addr === false) {
                return 'unknown';
            }

            return empty($addr) ? 'dead' : 'no_mx';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Motivo leggibile per cui un invio risulta non consegnato (usa gli eventi SES registrati).
     */
    public function describe(CampaignSend $send, ?Collection $events = null): string
    {
        if ($send->bounced_at) {
            $diagnostic = $events?->firstWhere('event_type', 'Bounce')?->diagnostic;
            $type = trim(($send->bounce_type ?? '') . ' / ' . ($send->bounce_subtype ?? ''), ' /');

            return 'Bounce' . ($type !== '' ? " {$type}" : '') . ($diagnostic ? ": {$diagnostic}" : '');
        }

        $delay = $events?->firstWhere('event_type', 'DeliveryDelay');
        if ($delay) {
            return 'Consegna ritardata' . ($delay->bounce_subtype ? " ({$delay->bounce_subtype})" : '')
                . ($delay->diagnostic ? ": {$delay->diagnostic}" : '');
        }

        $reject = $events?->firstWhere('event_type', 'Reject');
        if ($reject) {
            return 'Rifiutato da SES' . ($reject->diagnostic ? ": {$reject->diagnostic}" : '');
        }

        if ($send->sent_at && $send->sent_at->gt(now()->subHours($this->graceHours()))) {
            return 'In attesa (inviato da meno di ' . $this->graceHours() . ' ore)';
        }

        return 'Nessun evento di consegna ricevuto';
    }
}
