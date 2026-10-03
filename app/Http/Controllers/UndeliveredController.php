<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Undelivered;
use App\Services\UndeliveredService;
use Illuminate\Http\Request;

class UndeliveredController extends Controller
{
    public function index(Request $request, UndeliveredService $service)
    {
        $query = Undelivered::active()->orderByDesc('flagged_at');

        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('domain')) {
            $query->where('domain', $request->domain);
        }

        $entries = $query->paginate(50)->withQueryString();

        $topDomains = Undelivered::active()
            ->selectRaw('domain, COUNT(*) as n')
            ->groupBy('domain')
            ->orderByDesc('n')
            ->limit(10)
            ->get();

        $total = Undelivered::active()->count();
        $toCheck = $this->domainsToCheck()->count('domain');

        $threshold = $service->threshold();
        $grace = $service->graceHours();
        $reliableSince = $service->reliableSince();

        return view('undelivered.index', compact('entries', 'topDomains', 'total', 'toCheck', 'threshold', 'grace', 'reliableSince'));
    }

    public function evaluate(UndeliveredService $service)
    {
        $new = $service->evaluate();

        return back()->with('success', $new > 0
            ? "Analisi completata: {$new} nuovi indirizzi segnalati."
            : 'Analisi completata: nessun nuovo indirizzo da segnalare.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'threshold'   => 'required|integer|min:1|max:20',
            'grace_hours' => 'required|integer|min:0|max:720',
        ]);

        Setting::set('undelivered_threshold', $data['threshold']);
        Setting::set('undelivered_grace_hours', $data['grace_hours']);

        return back()->with('success', 'Impostazioni della regola salvate.');
    }

    /** Controllo DNS a piccoli gruppi di domini; il browser lo ripete finché ne restano. */
    public function checkMx(UndeliveredService $service)
    {
        $domains = $this->domainsToCheck()->limit(8)->pluck('domain');

        foreach ($domains as $domain) {
            Undelivered::where('domain', $domain)->update([
                'mx_status'     => $service->checkDomain($domain),
                'mx_checked_at' => now(),
            ]);
        }

        return response()->json([
            'checked'   => $domains->count(),
            'remaining' => $this->domainsToCheck()->count('domain'),
        ]);
    }

    /** Riabilita: l'indirizzo torna a ricevere; conta solo quanto accade dopo. */
    public function reinstate(Undelivered $undelivered)
    {
        $undelivered->update(['cleared_at' => now()]);

        return back()->with('success', "«{$undelivered->email}» riabilitato.");
    }

    /** Sposta in blacklist (blocco per tutti) e toglie dall'elenco dei non consegnati. */
    public function blacklist(Undelivered $undelivered)
    {
        $lists = MailList::whereIn('id', Subscriber::where('email', $undelivered->email)->pluck('list_id'))
            ->get()
            ->map(fn($l) => ['id' => $l->id, 'name' => $l->name])
            ->toArray();

        Blacklist::updateOrCreate(
            ['email' => strtolower($undelivered->email)],
            [
                'list_ids'   => $lists,
                'reason'     => "Non consegnato in {$undelivered->consecutive} invii consecutivi",
                'created_at' => now(),
            ]
        );

        $undelivered->delete();

        return back()->with('success', "«{$undelivered->email}» aggiunto alla blacklist.");
    }

    /** Domini segnalati e non ancora controllati (o controllati più di 7 giorni fa). */
    private function domainsToCheck()
    {
        return Undelivered::active()
            ->where(fn($q) => $q->whereNull('mx_checked_at')->orWhere('mx_checked_at', '<', now()->subDays(7)))
            ->distinct();
    }
}
