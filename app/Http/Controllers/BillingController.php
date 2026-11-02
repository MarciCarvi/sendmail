<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request, BillingService $billing)
    {
        $years = $billing->years();
        $year  = (int) $request->input('anno', now()->year);
        if (!in_array($year, $years, true)) {
            $year = (int) now()->year;
        }

        return view('billing.index', [
            'data'  => $billing->year($year),
            'years' => $years,
        ]);
    }

    /** Tariffa e cambio valgono per l'anno che si sta guardando: gli altri anni tengono i propri. */
    public function settings(Request $request, BillingService $billing)
    {
        $data = $request->validate([
            'usd_per_1000' => 'required|numeric|min:0|max:1000',
            'usd_per_eur'  => 'required|numeric|min:0.0001|max:1000',
            'anno'         => 'required|integer|min:2000|max:2100',
        ]);
        $year = (int) $data['anno'];

        $changes = ['usd_per_1000' => (float) $data['usd_per_1000']];

        // Un cambio modificato a mano non è più quello della BCE: lo si dice, con la data di oggi
        if (abs((float) $data['usd_per_eur'] - $billing->rates($year)['usd_per_eur']) > 1e-9) {
            $changes += [
                'usd_per_eur' => (float) $data['usd_per_eur'],
                'rate_date'   => now()->toDateString(),
                'rate_source' => 'manuale',
            ];
        }

        $billing->saveRates($year, $changes);

        return redirect()->route('billing.index', ['anno' => $year])
            ->with('success', "Tariffa e cambio salvati per il {$year}.");
    }

    public function refreshRate(Request $request, BillingService $billing)
    {
        $year = (int) $request->input('anno', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        try {
            [$rate, $date] = $billing->fetchEcbRate();
        } catch (\Throwable $e) {
            return back()->with('error', 'Non riesco a leggere il cambio dalla BCE: ' . $e->getMessage());
        }

        $billing->saveRates($year, ['usd_per_eur' => $rate, 'rate_date' => $date, 'rate_source' => 'BCE']);

        return redirect()->route('billing.index', ['anno' => $year])
            ->with('success', "Cambio del {$year} aggiornato dalla BCE: 1 € = " . number_format($rate, 4, ',', '.') . ' $ (' . date('d/m/Y', strtotime($date)) . ').');
    }
}
