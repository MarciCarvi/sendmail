<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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
            'data'       => $billing->year($year),
            'years'      => $years,
            'usdPer1000' => $billing->usdPer1000(),
            'usdPerEur'  => $billing->usdPerEur(),
            'rateDate'   => $billing->rateDate(),
            'rateSource' => $billing->rateSource(),
        ]);
    }

    public function settings(Request $request, BillingService $billing)
    {
        $data = $request->validate([
            'usd_per_1000' => 'required|numeric|min:0|max:1000',
            'usd_per_eur'  => 'required|numeric|min:0.0001|max:1000',
            'anno'         => 'nullable|integer',
        ]);

        Setting::set('billing_usd_per_1000', $data['usd_per_1000']);

        // Un cambio modificato a mano non è più quello della BCE: lo si dice, con la data di oggi
        if (abs((float) $data['usd_per_eur'] - $billing->usdPerEur()) > 1e-9) {
            Setting::set('billing_usd_per_eur', $data['usd_per_eur']);
            Setting::set('billing_rate_date', now()->toDateString());
            Setting::set('billing_rate_source', 'manuale');
        }

        return redirect()->route('billing.index', ['anno' => $data['anno'] ?? null])
            ->with('success', 'Tariffa e cambio salvati.');
    }

    public function refreshRate(Request $request, BillingService $billing)
    {
        try {
            [$rate, $date] = $billing->fetchEcbRate();
        } catch (\Throwable $e) {
            return back()->with('error', 'Non riesco a leggere il cambio dalla BCE: ' . $e->getMessage());
        }

        Setting::set('billing_usd_per_eur', $rate);
        Setting::set('billing_rate_date', $date);
        Setting::set('billing_rate_source', 'BCE');

        return back()->with('success', 'Cambio aggiornato dalla BCE: 1 € = ' . number_format($rate, 4, ',', '.') . ' $ (' . date('d/m/Y', strtotime($date)) . ').');
    }
}
