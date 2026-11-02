<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Costo di invio stimato: email inviate per mese e per cliente (profilo di invio) × tariffa AWS.
 * I mesi sono quelli UTC, come la fatturazione AWS (l'app salva sent_at in UTC).
 *
 * I conteggi stanno nel registro sm_billing_ledger, scritto a ogni invio: non dipendono da sm_campaign_sends,
 * che si cancella con campagne, liste e iscritti. Tariffa e cambio sono per anno, così quelli passati non
 * cambiano quando si aggiorna il cambio o la tariffa.
 */
class BillingService
{
    // Valori iniziali (tariffa indicata dall'utente, cambio BCE del 30/10/2026): non cambiarli mai, gli anni
    // senza valori propri li usano ancora.
    public const DEFAULT_USD_PER_1000 = 0.16;
    public const DEFAULT_USD_PER_EUR  = 1.1172;
    public const DEFAULT_RATE_DATE    = '2026-10-30';

    public const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    // Palette categorica validata (light): un colore per cliente, assegnato in ordine di profilo e mai ciclato
    public const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
    public const COLOR_OTHERS     = '#898781';
    public const COLOR_NO_PROFILE = '#c3c2b7';

    /**
     * Tariffa e cambio validi per un anno: quelli salvati per quell'anno o, in mancanza, per l'ultimo anno
     * precedente che ne ha (altrimenti i valori della 1.10.0 o quelli iniziali). `usd_per_eur` = dollari per 1 €
     * (convenzione BCE). `from_year` dice da quale anno vengono (null = base).
     */
    public function rates(int $year): array
    {
        $stored = $this->storedRates();
        $years  = array_filter(array_keys($stored), fn ($y) => $y <= $year);

        if ($years) {
            $from = max($years);

            return $this->normalize($stored[$from]) + ['from_year' => $from];
        }

        // Chiavi singole scritte dalla 1.10.0, prima che i valori fossero per anno
        return $this->normalize([
            'usd_per_1000' => Setting::get('billing_usd_per_1000'),
            'usd_per_eur'  => Setting::get('billing_usd_per_eur'),
            'rate_date'    => Setting::get('billing_rate_date'),
            'rate_source'  => Setting::get('billing_rate_source'),
        ]) + ['from_year' => null];
    }

    /** Salva (unendoli a quelli già validi) i valori di un anno; da quel momento l'anno ha i propri. */
    public function saveRates(int $year, array $changes): void
    {
        $stored  = $this->storedRates();
        $current = $this->rates($year);
        unset($current['from_year']);

        $stored[$year] = array_merge($current, $changes);
        ksort($stored);

        Setting::set('billing_rates', json_encode($stored));
    }

    /** @return array<int, array> anno => valori */
    private function storedRates(): array
    {
        $raw = json_decode((string) Setting::get('billing_rates', ''), true);
        $out = [];

        foreach (is_array($raw) ? $raw : [] as $year => $values) {
            if (is_array($values)) {
                $out[(int) $year] = $values;
            }
        }

        return $out;
    }

    private function normalize(array $r): array
    {
        $price = (float) ($r['usd_per_1000'] ?? self::DEFAULT_USD_PER_1000);
        $rate  = (float) ($r['usd_per_eur'] ?? self::DEFAULT_USD_PER_EUR);

        return [
            'usd_per_1000' => $price >= 0 ? $price : self::DEFAULT_USD_PER_1000,
            'usd_per_eur'  => $rate > 0 ? $rate : self::DEFAULT_USD_PER_EUR,
            'rate_date'    => (string) ($r['rate_date'] ?? self::DEFAULT_RATE_DATE),
            'rate_source'  => (string) ($r['rate_source'] ?? 'BCE'),
        ];
    }

    /**
     * Conta un invio riuscito nel registro (mese corrente, profilo della campagna). Non lancia mai
     * eccezioni: un problema qui non deve fermare né far fallire un invio.
     */
    public function recordSent(Campaign $campaign, int $emails = 1): void
    {
        try {
            $period = [
                'year'              => (int) now()->year,
                'month'             => (int) now()->month,
                'sender_profile_id' => (int) $campaign->sender_profile_id,
            ];
            $name  = $campaign->senderProfile?->name;
            $extra = ['updated_at' => now()] + ($name !== null ? ['profile_name' => $name] : []);

            $row = fn () => DB::table('sm_billing_ledger')->where($period);

            if ($row()->increment('emails', $emails, $extra) === 0) {
                DB::table('sm_billing_ledger')->insertOrIgnore(
                    $period + ['profile_name' => $name, 'emails' => 0, 'created_at' => now(), 'updated_at' => now()]
                );
                $row()->increment('emails', $emails, $extra);
            }
        } catch (\Throwable $e) {
            Log::warning('Registro fatturazione: conteggio non salvato', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
        }
    }

    /** Anni selezionabili: dal primo con invii a quello corrente. */
    public function years(): array
    {
        $current = (int) now()->year;
        $first   = DB::table('sm_billing_ledger')->min('year');

        return range($current, min($first ? (int) $first : $current, $current));
    }

    /**
     * @return array{
     *   year:int, rates:array, clients:array, series:array, months:array, totalEmails:int, totalUsd:float, totalEur:float
     * }
     */
    public function year(int $year): array
    {
        $rates = $this->rates($year);
        $price = $rates['usd_per_1000'];
        $rate  = $rates['usd_per_eur'];

        $rows = DB::table('sm_billing_ledger')
            ->where('year', $year)
            ->get(['month', 'sender_profile_id', 'profile_name', 'emails']);

        // Il colore segue il cliente (posizione tra tutti i profili, anche eliminati), non la sua classifica nell'anno
        $profiles = DB::table('sm_sender_profiles')->pluck('name', 'id');
        $known = $profiles->keys()->map(fn ($id) => (int) $id)
            ->merge(DB::table('sm_billing_ledger')->where('sender_profile_id', '>', 0)->distinct()->pluck('sender_profile_id')->map(fn ($id) => (int) $id))
            ->unique()->sort()->values();
        $slot = array_flip($known->all());

        $clients = [];
        $perMonth = array_fill(1, 12, []);   // mese => [chiave cliente => email]
        foreach ($rows as $r) {
            $id  = (int) $r->sender_profile_id;
            $key = $id === 0 ? 'none' : (string) $id;

            if (!isset($clients[$key])) {
                $name = $id === 0
                    ? 'Senza profilo'
                    : ($profiles[$id] ?? (($r->profile_name ?: "Profilo #{$id}") . ' (eliminato)'));
                $clients[$key] = $this->describeClient($id, $name, $slot);
            }
            $perMonth[(int) $r->month][$key] = ($perMonth[(int) $r->month][$key] ?? 0) + (int) $r->emails;
            $clients[$key]['emails'] += (int) $r->emails;
        }

        // Ordine delle serie: profili nell'ordine dei colori, poi "Altri", poi "Senza profilo"
        uasort($clients, fn ($a, $b) => $a['order'] <=> $b['order']);

        $months = [];
        $cumulative = 0;
        $currentMonth = $year === (int) now()->year ? (int) now()->month : ($year < (int) now()->year ? 12 : 0);
        for ($m = 1; $m <= 12; $m++) {
            $emails = array_sum($perMonth[$m]);
            $cumulative += $emails;
            $months[$m] = [
                'month'      => $m,
                'emails'     => $emails,
                'usd'        => $emails * $price / 1000,
                'eur'        => $emails * $price / 1000 / $rate,
                'cumEmails'  => $cumulative,
                'cumEur'     => $cumulative * $price / 1000 / $rate,
                'future'     => $m > $currentMonth,
                'perClient'  => $perMonth[$m],
            ];
        }

        foreach ($clients as &$c) {
            $c['usd'] = $c['emails'] * $price / 1000;
            $c['eur'] = $c['emails'] * $price / 1000 / $rate;
        }
        unset($c);

        return [
            'year'        => $year,
            'rates'       => $rates,
            'clients'     => $clients,
            'series'      => $this->chartSeries($clients, $months),
            'months'      => $months,
            'totalEmails' => $cumulative,
            'totalUsd'    => $cumulative * $price / 1000,
            'totalEur'    => $cumulative * $price / 1000 / $rate,
        ];
    }

    /** Una serie per cliente (12 valori, gennaio-dicembre); oltre i colori disponibili i clienti si fondono in «Altri clienti». */
    private function chartSeries(array $clients, array $months): array
    {
        $series = [];
        foreach ($clients as $key => $c) {
            $folded = $key !== 'none' && $c['order'] >= count(self::PALETTE);
            $id     = $folded ? 'others' : $key;

            $series[$id] ??= [
                'name'  => $folded ? 'Altri clienti' : $c['name'],
                'color' => $folded ? self::COLOR_OTHERS : $c['color'],
                'order' => $folded ? count(self::PALETTE) : $c['order'],
                'data'  => array_fill(0, 12, 0),
            ];

            foreach ($months as $m => $row) {
                $series[$id]['data'][$m - 1] += $row['perClient'][$key] ?? 0;
            }
        }

        uasort($series, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_values($series);
    }

    private function describeClient(int $profileId, string $name, array $slot): array
    {
        if ($profileId === 0) {
            return ['name' => $name, 'color' => self::COLOR_NO_PROFILE, 'order' => 1000, 'emails' => 0];
        }

        $i = $slot[$profileId] ?? count(self::PALETTE);

        return [
            'name'   => $name,
            'color'  => self::PALETTE[$i] ?? self::COLOR_OTHERS,
            'order'  => $i,
            'emails' => 0,
        ];
    }

    /** Legge il cambio USD/EUR dal feed giornaliero della BCE. Restituisce [tasso, data] o lancia un'eccezione. */
    public function fetchEcbRate(): array
    {
        $xml = Http::timeout(10)->get(self::ECB_URL)->throw()->body();

        if (!preg_match("/currency=['\"]USD['\"]\s+rate=['\"]([0-9.]+)['\"]/", $xml, $rate)
            || !preg_match("/<Cube\s+time=['\"](\d{4}-\d{2}-\d{2})['\"]/", $xml, $date)) {
            throw new \RuntimeException('Il feed della BCE non contiene il cambio USD.');
        }

        return [(float) $rate[1], $date[1]];
    }
}
