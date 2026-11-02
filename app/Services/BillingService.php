<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Costo di invio stimato: email inviate per mese e per cliente (profilo di invio) × tariffa AWS.
 * I mesi sono quelli UTC, come la fatturazione AWS (l'app salva sent_at in UTC).
 */
class BillingService
{
    // Valori iniziali: tariffa indicata dall'utente, cambio BCE del 30/10/2026
    public const DEFAULT_USD_PER_1000 = 0.16;
    public const DEFAULT_USD_PER_EUR  = 1.1172;
    public const DEFAULT_RATE_DATE    = '2026-10-30';

    public const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    // Palette categorica validata (light): un colore per cliente, assegnato in ordine di profilo e mai ciclato
    public const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
    public const COLOR_OTHERS     = '#898781';
    public const COLOR_NO_PROFILE = '#c3c2b7';

    public function usdPer1000(): float
    {
        return (float) Setting::get('billing_usd_per_1000', self::DEFAULT_USD_PER_1000);
    }

    /** Dollari USA per 1 euro (convenzione BCE). */
    public function usdPerEur(): float
    {
        $rate = (float) Setting::get('billing_usd_per_eur', self::DEFAULT_USD_PER_EUR);

        return $rate > 0 ? $rate : self::DEFAULT_USD_PER_EUR;
    }

    public function rateDate(): string
    {
        return (string) Setting::get('billing_rate_date', self::DEFAULT_RATE_DATE);
    }

    public function rateSource(): string
    {
        return (string) Setting::get('billing_rate_source', 'BCE');
    }

    /** Anni selezionabili: dal primo invio a quello corrente. */
    public function years(): array
    {
        $first = DB::table('sm_campaign_sends')->where('status', 'sent')->min('sent_at');
        $from  = $first ? (int) substr($first, 0, 4) : (int) now()->year;

        return range((int) now()->year, min($from, (int) now()->year));
    }

    /**
     * @return array{
     *   year:int, clients:array, months:array, totalEmails:int, totalUsd:float, totalEur:float
     * }
     */
    public function year(int $year): array
    {
        $price = $this->usdPer1000();
        $rate  = $this->usdPerEur();

        $rows = DB::table('sm_campaign_sends as s')
            ->join('sm_campaigns as c', 'c.id', '=', 's.campaign_id')
            ->where('s.status', 'sent')
            ->whereBetween('s.sent_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
            ->selectRaw('MONTH(s.sent_at) as month, c.sender_profile_id as profile_id, COUNT(*) as n')
            ->groupByRaw('MONTH(s.sent_at), c.sender_profile_id')
            ->get();

        // Il colore segue il cliente (posizione tra tutti i profili), non la sua classifica nell'anno
        $profiles = DB::table('sm_sender_profiles')->orderBy('id')->pluck('name', 'id');
        $slot = [];
        foreach ($profiles->keys() as $i => $id) {
            $slot[$id] = $i;
        }

        $clients = [];
        $perMonth = array_fill(1, 12, []);   // mese => [chiave cliente => email]
        foreach ($rows as $r) {
            $key = $r->profile_id === null ? 'none' : (string) $r->profile_id;
            if (!isset($clients[$key])) {
                $clients[$key] = $this->describeClient($r->profile_id, $profiles, $slot);
            }
            $perMonth[(int) $r->month][$key] = ($perMonth[(int) $r->month][$key] ?? 0) + (int) $r->n;
            $clients[$key]['emails'] += (int) $r->n;
        }

        // Ordine di impilamento: profili nell'ordine dei colori, poi "Altri", poi "Senza profilo"
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

    private function describeClient(?int $profileId, \Illuminate\Support\Collection $profiles, array $slot): array
    {
        if ($profileId === null) {
            return ['name' => 'Senza profilo', 'color' => self::COLOR_NO_PROFILE, 'order' => 1000, 'emails' => 0];
        }

        $name = $profiles[$profileId] ?? "Profilo #{$profileId}";
        $i    = $slot[$profileId] ?? count(self::PALETTE);

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
