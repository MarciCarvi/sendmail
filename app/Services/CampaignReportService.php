<?php

namespace App\Services;

use App\Models\Blacklist;
use App\Models\Campaign;
use App\Models\CampaignClick;
use App\Models\CampaignOpen;
use App\Models\CampaignSend;
use App\Models\SesEvent;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Undelivered;
use App\Models\Unsubscribe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Numeri e documenti dei rapporti di campagna: riepilogo per il cliente, CSV per destinatario
 * (uso tecnico) e cronologia del singolo destinatario.
 */
class CampaignReportService
{
    public function __construct(private UndeliveredService $undelivered)
    {
    }

    /** Data/ora nel fuso dei rapporti. */
    public function local(?Carbon $date): ?Carbon
    {
        return $date?->copy()->setTimezone(config('sendmail.report_timezone', 'Europe/Rome'));
    }

    /** Marchio mostrato nei rapporti: nome e logo (incorporato come data URI). */
    public function brand(): array
    {
        $name = trim((string) Setting::get('report_brand_name', '')) ?: (string) Setting::get('app_name', config('app.name'));

        $logo = null;
        $path = Setting::get('report_logo');
        if ($path && Storage::disk('public')->exists($path)) {
            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png'         => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp'        => 'image/webp',
                default       => null,
            };
            if ($mime) {
                $logo = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
            }
        }

        // Senza logo caricato in Impostazioni si usa quello predefinito fornito con l'applicazione
        if (!$logo && is_file($default = public_path('img/report-logo.png'))) {
            $logo = 'data:image/png;base64,' . base64_encode(file_get_contents($default));
        }

        return ['name' => $name, 'logo' => $logo];
    }

    /** Motivo leggibile per un invio non consegnato: [gruppo, etichetta]. */
    public function reason(string $status, bool $bounced, ?string $type, ?string $subtype, bool $recent): array
    {
        if ($status === 'failed') {
            return ['Non inviata', 'Non inviata: rifiutata al momento dell\'invio'];
        }
        if ($status === 'pending') {
            return ['Non inviata', 'Invio non completato'];
        }

        if ($bounced) {
            if ($type === 'Transient') {
                return ['Problema temporaneo', match ($subtype) {
                    'MailboxFull'        => 'Casella del destinatario piena',
                    'MessageTooLarge'    => 'Messaggio troppo grande per il destinatario',
                    'ContentRejected'    => 'Contenuto respinto dal server del destinatario',
                    'AttachmentRejected' => 'Allegato respinto dal server del destinatario',
                    default              => 'Problema temporaneo del server del destinatario',
                }];
            }
            if ($type === 'Permanent') {
                return ['Indirizzo non valido', match ($subtype) {
                    'NoEmail'                           => 'Indirizzo email non valido',
                    'Suppressed', 'OnAccountSuppressionList' => 'Indirizzo escluso per precedenti rimbalzi',
                    default                             => 'Indirizzo inesistente o non raggiungibile',
                }];
            }

            return ['Motivo non determinato', 'Rimbalzo con motivo non determinato dal server del destinatario'];
        }

        return $recent
            ? ['In attesa', 'In attesa di esito (inviata da poco)']
            : ['Nessuna conferma', 'Nessuna conferma di consegna dal server del destinatario'];
    }

    /**
     * I numeri dei sei riquadri (inviati, consegnati, aperture, click, disiscrizioni, problemi):
     * sono gli stessi nel report del software e nel rapporto per il cliente.
     */
    public function kpis(Campaign $campaign): array
    {
        $id = $campaign->id;
        $sends = fn() => CampaignSend::where('campaign_id', $id);

        $sent = $sends()->where('status', 'sent')->count();
        // consegnati = consegna confermata e nessun bounce successivo (i bounce asincroni arrivano dopo la consegna)
        $delivered = $sends()->whereNotNull('delivered_at')->whereNull('bounced_at')->count();
        $failed = $sends()->where('status', 'failed')->count();
        $bounced = $sends()->whereNotNull('bounced_at')->count();
        $bouncedPermanent = $sends()->where('bounce_type', 'Permanent')->count();
        $complaints = $sends()->whereNotNull('complained_at')->count();
        $undeliveredCount = $sends()->where('status', 'sent')
            ->where(fn($q) => $q->whereNull('delivered_at')->orWhereNotNull('bounced_at'))->count();

        $uniqueOpens = CampaignOpen::where('campaign_id', $id)->distinct()->count('subscriber_id');
        $totalOpens = CampaignOpen::where('campaign_id', $id)->count();
        $uniqueClicks = CampaignClick::where('campaign_id', $id)->distinct()->count('subscriber_id');
        $totalClicks = CampaignClick::where('campaign_id', $id)->count();

        // disiscritti di questa campagna: iscritti raggiunti che si sono disiscritti dal primo invio in poi
        $firstSent = $sends()->min('sent_at');
        $unsubscribed = $firstSent
            ? Subscriber::whereHas('sends', fn($q) => $q->where('campaign_id', $id)->where('status', 'sent'))
                ->where('status', 'unsubscribed')
                ->where('unsubscribed_at', '>=', $firstSent)
                ->count()
            : 0;

        $rate = fn($n) => $sent > 0 ? round($n / $sent * 100, 1) : 0;

        return [
            'sent' => $sent, 'delivered' => $delivered, 'deliveryRate' => $rate($delivered),
            'failed' => $failed, 'bounced' => $bounced, 'bouncedPermanent' => $bouncedPermanent,
            'complaints' => $complaints, 'undeliveredCount' => $undeliveredCount,
            'uniqueOpens' => $uniqueOpens, 'totalOpens' => $totalOpens, 'openRate' => $rate($uniqueOpens),
            'uniqueClicks' => $uniqueClicks, 'totalClicks' => $totalClicks, 'clickRate' => $rate($uniqueClicks),
            'unsubscribed' => $unsubscribed, 'unsubRate' => $rate($unsubscribed),
        ];
    }

    /** Tutti i numeri del rapporto per il cliente. */
    public function summary(Campaign $campaign): array
    {
        $id = $campaign->id;
        $sends = fn() => CampaignSend::where('campaign_id', $id);
        $grace = $this->undelivered->graceHours();
        $cutoff = now()->subHours($grace);

        $k = $this->kpis($campaign);
        $sent = $k['sent'];
        $notDelivered = max(0, $sent - $k['delivered']);
        $pct = fn($n, $of) => $of > 0 ? round($n / $of * 100, 1) : 0;

        // Residuo: gli invii accettati da SES che non risultano consegnati, raggruppati per motivo
        $groups = [];
        $rows = $sends()
            ->where('status', 'sent')
            ->where(fn($q) => $q->whereNull('delivered_at')->orWhereNotNull('bounced_at'))
            ->selectRaw('status, bounce_type, bounce_subtype, (bounced_at IS NOT NULL) as bounced, (sent_at > ?) as recent, COUNT(*) as n', [$cutoff])
            ->groupBy('status', 'bounce_type', 'bounce_subtype', 'bounced', 'recent')
            ->get();

        foreach ($rows as $r) {
            [$group, $label] = $this->reason($r->status, (bool) $r->bounced, $r->bounce_type, $r->bounce_subtype, (bool) $r->recent);
            $groups[$label] = [
                'group' => $group,
                'label' => $label,
                'n'     => ($groups[$label]['n'] ?? 0) + $r->n,
            ];
        }
        $reasons = collect($groups)->sortByDesc('n')->values()
            ->map(fn($r) => $r + ['pct' => $pct($r['n'], $sent)])->all();

        $topLinks = CampaignClick::where('campaign_id', $id)
            ->selectRaw('original_url, COUNT(*) as total, COUNT(DISTINCT subscriber_id) as uniq')
            ->groupBy('original_url')
            ->orderByDesc('uniq')->orderByDesc('total')
            ->limit(10)
            ->get();

        $firstSent = $sends()->min('sent_at');
        $lastSent = $sends()->max('sent_at');

        return $k + [
            'notDelivered'    => $notDelivered,
            'notDeliveredPct' => $pct($notDelivered, $sent),
            'deliveredPct'    => $pct($k['delivered'], $sent),
            'reasons'         => $reasons,
            'notSent'         => $k['failed'],                                  // rifiutate da SES all'invio
            'inQueue'         => $sends()->where('status', 'pending')->count(), // invio non completato
            'topLinks'        => $topLinks,
            'firstSent'       => $firstSent ? $this->local(Carbon::parse($firstSent)) : null,
            'lastSent'        => $lastSent ? $this->local(Carbon::parse($lastSent)) : null,
            'inProgress'      => in_array($campaign->status, ['sending', 'paused'], true),
            'graceHours'      => $grace,
            'generatedAt'     => $this->local(now()),
        ];
    }

    /** Eventi di bounce, ritardo e rifiuto per message_id. */
    public function eventsFor(array $messageIds): Collection
    {
        if (empty($messageIds)) {
            return collect();
        }

        return SesEvent::whereIn('message_id', $messageIds)
            ->whereIn('event_type', ['Bounce', 'DeliveryDelay', 'Reject'])
            ->orderBy('id')
            ->get()
            ->groupBy('message_id');
    }

    public static function csvHeader(): array
    {
        return [
            'email', 'nome', 'cognome', 'azienda', 'stato_invio', 'inviato_il', 'consegnato_il',
            'esito', 'dettaglio_esito', 'bounce_tipo', 'bounce_sottotipo', 'messaggio_server',
            'aperture', 'prima_apertura', 'click', 'primo_click', 'primo_link', 'disiscritto', 'reclamo',
        ];
    }

    /** Righe del CSV per destinatario (una per invio), a blocchi per non caricare tutto in memoria. */
    public function recipientRows(Campaign $campaign): \Generator
    {
        $grace = $this->undelivered->graceHours();
        $fmt = fn(?Carbon $d) => $d ? $this->local($d)->format('Y-m-d H:i:s') : '';

        $query = CampaignSend::with('subscriber')->where('campaign_id', $campaign->id)->orderBy('id');

        $buffer = [];
        foreach ($query->lazyById(500) as $send) {
            // lazyById restituisce un invio alla volta: accumula a blocchi per i calcoli aggregati
            $buffer[] = $send;
            if (count($buffer) < 500) {
                continue;
            }
            yield from $this->rowsForChunk($campaign, collect($buffer), $grace, $fmt);
            $buffer = [];
        }

        if (!empty($buffer)) {
            yield from $this->rowsForChunk($campaign, collect($buffer), $grace, $fmt);
        }
    }

    private function rowsForChunk(Campaign $campaign, Collection $sends, int $grace, callable $fmt): \Generator
    {
        $subscriberIds = $sends->pluck('subscriber_id')->all();
        $events = $this->eventsFor($sends->pluck('message_id')->filter()->all());

        $opens = CampaignOpen::where('campaign_id', $campaign->id)->whereIn('subscriber_id', $subscriberIds)
            ->selectRaw('subscriber_id, COUNT(*) as n, MIN(opened_at) as first_at')->groupBy('subscriber_id')->get()->keyBy('subscriber_id');
        $clicks = CampaignClick::where('campaign_id', $campaign->id)->whereIn('subscriber_id', $subscriberIds)
            ->orderBy('clicked_at')->get()->groupBy('subscriber_id');

        foreach ($sends as $s) {
            $sub = $s->subscriber;
            $recent = $s->sent_at && $s->sent_at->gt(now()->subHours($grace));
            $delivered = $s->status === 'sent' && $s->delivered_at && !$s->bounced_at;

            [$group, $label] = $this->reason($s->status, (bool) $s->bounced_at, $s->bounce_type, $s->bounce_subtype, (bool) $recent);

            $esito = $delivered ? 'Consegnato' : match (true) {
                $s->status === 'pending'          => 'In coda',
                $s->status === 'failed'           => 'Non inviato',
                $s->bounce_type === 'Permanent'   => 'Bounce permanente',
                $s->bounce_type === 'Transient'   => 'Bounce temporaneo',
                (bool) $s->bounced_at             => 'Bounce non determinato',
                (bool) $recent                    => 'In attesa di esito',
                default                           => 'Nessuna conferma',
            };

            $bounceEvent = $events->get($s->message_id)?->firstWhere('event_type', 'Bounce')
                ?? $events->get($s->message_id)?->firstWhere('event_type', 'DeliveryDelay');
            $myClicks = $clicks->get($s->subscriber_id, collect());
            $myOpens = $opens->get($s->subscriber_id);

            yield [
                $this->safe($sub?->email), $this->safe($sub?->first_name), $this->safe($sub?->last_name), $this->safe($sub?->company),
                match ($s->status) { 'sent' => 'Inviato', 'failed' => 'Non inviato', default => 'In coda' },
                $fmt($s->sent_at), $fmt($s->delivered_at),
                $esito, $delivered ? '' : $label,
                $s->bounce_type ?? '', $s->bounce_subtype ?? '', $this->safe($bounceEvent?->diagnostic),
                $myOpens?->n ?? 0, $myOpens ? $fmt(Carbon::parse($myOpens->first_at)) : '',
                $myClicks->count(), $myClicks->isNotEmpty() ? $fmt($myClicks->first()->clicked_at) : '',
                $this->safe($myClicks->first()?->original_url),
                $sub?->status === 'unsubscribed' ? 'sì' : 'no',
                $s->complained_at ? 'sì' : 'no',
            ];
        }
    }

    /** Neutralizza le formule nei CSV (= + - @). */
    public function safe(?string $value): string
    {
        $value = (string) $value;

        return ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) ? "'" . $value : $value;
    }

    /** Cronologia completa di un indirizzo: iscrizioni, stato e, per ogni invio, cosa è successo. */
    public function timeline(string $email): array
    {
        $email = strtolower(trim($email));
        $subscribers = Subscriber::with('list')->where('email', $email)->get();
        $ids = $subscribers->pluck('id')->all();

        $sends = CampaignSend::with('campaign')->whereIn('subscriber_id', $ids)
            ->orderByDesc('sent_at')->orderByDesc('id')->get();

        $events = SesEvent::whereIn('message_id', $sends->pluck('message_id')->filter()->all())
            ->orderBy('id')->get()->groupBy('message_id');
        $opens = CampaignOpen::whereIn('subscriber_id', $ids)->orderBy('opened_at')->get()->groupBy('campaign_id');
        $clicks = CampaignClick::whereIn('subscriber_id', $ids)->orderBy('clicked_at')->get()->groupBy('campaign_id');

        $items = $sends->map(function ($s) use ($events, $opens, $clicks) {
            $steps = [];
            $add = function (?Carbon $at, string $label, string $kind) use (&$steps) {
                if ($at) {
                    $steps[] = ['at' => $at, 'label' => $label, 'kind' => $kind];
                }
            };

            $add($s->sent_at, $s->status === 'failed' ? 'Invio rifiutato da SES' : 'Inviata', 'neutral');
            $msgEvents = $events->get($s->message_id, collect());

            $add($s->delivered_at, 'Consegnata', 'ok');
            foreach ($msgEvents as $e) {
                if ($e->event_type === 'Bounce') {
                    $add($e->occurred_at, 'Bounce ' . trim(($e->bounce_type ?? '') . ' / ' . ($e->bounce_subtype ?? ''), ' /')
                        . ($e->diagnostic ? ': ' . $e->diagnostic : ''), 'bad');
                } elseif ($e->event_type === 'DeliveryDelay') {
                    $add($e->occurred_at, 'Consegna ritardata' . ($e->bounce_subtype ? " ({$e->bounce_subtype})" : ''), 'warn');
                } elseif ($e->event_type === 'Reject') {
                    $add($e->occurred_at, 'Rifiutata da SES' . ($e->diagnostic ? ': ' . $e->diagnostic : ''), 'bad');
                } elseif ($e->event_type === 'Complaint') {
                    $add($e->occurred_at, 'Segnalata come spam', 'bad');
                }
            }
            if ($s->bounced_at && !$msgEvents->contains('event_type', 'Bounce')) {
                $add($s->bounced_at, 'Bounce ' . trim(($s->bounce_type ?? '') . ' / ' . ($s->bounce_subtype ?? ''), ' /'), 'bad');
            }
            if ($s->complained_at && !$msgEvents->contains('event_type', 'Complaint')) {
                $add($s->complained_at, 'Segnalata come spam', 'bad');
            }

            foreach ($opens->get($s->campaign_id, collect()) as $o) {
                $add($o->opened_at, 'Aperta', 'info');
            }
            foreach ($clicks->get($s->campaign_id, collect()) as $c) {
                $add($c->clicked_at, 'Click: ' . $c->original_url, 'info');
            }

            usort($steps, fn($a, $b) => $a['at'] <=> $b['at']);

            return [
                'campaign' => $s->campaign,
                'status'   => $s->status,
                'steps'    => array_map(fn($st) => $st + ['local' => $this->local($st['at'])], $steps),
            ];
        })->all();

        return [
            'email'         => $email,
            'subscriptions' => $subscribers,
            'blacklisted'   => $email !== '' && Blacklist::isBlacklisted($email),
            'undelivered'   => Undelivered::active()->where('email', $email)->first(),
            'unsubscribes'  => $email !== '' ? Unsubscribe::where('email', $email)->get() : collect(),
            'items'         => $items,
        ];
    }
}
