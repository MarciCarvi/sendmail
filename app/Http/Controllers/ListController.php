<?php

namespace App\Http\Controllers;

use App\Models\MailList;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index()
    {
        $lists = MailList::withCount(['subscribers' => fn($q) => $q->where('status', 'subscribed')])
            ->latest()
            ->get();

        return view('lists.index', compact('lists'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'from_name'  => 'required|string|max:100',
            'from_email' => 'required|email',
            'reply_to'   => 'nullable|email',
        ]);

        MailList::create($request->only('name', 'from_name', 'from_email', 'reply_to', 'double_optin'));

        return redirect()->route('lists.index')->with('success', 'Lista creata.');
    }

    public function update(Request $request, MailList $list)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'from_name'  => 'required|string|max:100',
            'from_email' => 'required|email',
            'reply_to'   => 'nullable|email',
        ]);

        $list->update($request->only('name', 'from_name', 'from_email', 'reply_to', 'double_optin'));

        return redirect()->route('lists.index')->with('success', 'Lista aggiornata.');
    }

    public function destroy(MailList $list)
    {
        $list->delete();
        return redirect()->route('lists.index')->with('success', 'Lista eliminata.');
    }
}
