<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class BlacklistController extends Controller
{
    public function index(Request $request)
    {
        $query = Blacklist::orderByDesc('created_at');

        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $entries = $query->paginate(50)->withQueryString();

        return view('blacklist.index', compact('entries'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subscriber_id' => 'required|exists:sm_subscribers,id',
            'reason'        => 'nullable|string|max:500',
        ]);

        $subscriber = Subscriber::findOrFail($request->subscriber_id);

        // Snapshot delle liste di appartenenza
        $lists = MailList::whereIn('id',
            Subscriber::where('email', $subscriber->email)->pluck('list_id')
        )->get()->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->toArray();

        Blacklist::updateOrCreate(
            ['email' => strtolower($subscriber->email)],
            ['list_ids' => $lists, 'reason' => $request->reason, 'created_at' => now()]
        );

        return back()->with('success', "«{$subscriber->email}» aggiunto alla blacklist.");
    }

    public function destroy(Blacklist $blacklist)
    {
        $blacklist->delete();
        return back()->with('success', "«{$blacklist->email}» rimosso dalla blacklist.");
    }
}
