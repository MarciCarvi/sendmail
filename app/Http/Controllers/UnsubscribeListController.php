<?php

namespace App\Http\Controllers;

use App\Models\MailList;
use App\Models\SenderProfile;
use App\Models\Subscriber;
use App\Models\Unsubscribe;
use Illuminate\Http\Request;

class UnsubscribeListController extends Controller
{
    public function index(Request $request)
    {
        $query = Unsubscribe::with('list')->orderByDesc('unsubscribed_at')->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('domain')) {
            $query->where('sender_domain', $request->domain);
        }

        $entries = $query->paginate(50)->withQueryString();
        $domains = Unsubscribe::distinct()->orderBy('sender_domain')->pluck('sender_domain');

        // Nome del cliente = profilo/i di invio il cui mittente è su quel dominio
        $clientNames = SenderProfile::orderBy('name')->get()
            ->groupBy(fn($p) => Unsubscribe::domainOf($p->from_email))
            ->map(fn($group) => $group->pluck('name')->join(', '));

        return view('unsubscribes.index', compact('entries', 'domains', 'clientNames'));
    }

    /**
     * Riabilita: l'indirizzo può tornare a ricevere campagne di quel cliente.
     * Rimette "iscritto" le sue righe disiscritte nelle liste con mittente su quel dominio.
     */
    public function destroy(Unsubscribe $unsubscribe)
    {
        $listIds = MailList::where('from_email', 'like', '%@' . $unsubscribe->sender_domain)->pluck('id');

        Subscriber::where('email', $unsubscribe->email)
            ->where('status', 'unsubscribed')
            ->whereIn('list_id', $listIds)
            ->update(['status' => 'subscribed', 'unsubscribed_at' => null]);

        $unsubscribe->delete();

        return back()->with('success', "«{$unsubscribe->email}» riabilitato per {$unsubscribe->sender_domain}.");
    }
}
