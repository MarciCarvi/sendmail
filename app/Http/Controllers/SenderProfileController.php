<?php

namespace App\Http\Controllers;

use App\Models\SenderProfile;
use Illuminate\Http\Request;

class SenderProfileController extends Controller
{
    public function index()
    {
        $profiles = SenderProfile::withCount('campaigns')->orderBy('name')->get();
        return view('sender-profiles.index', compact('profiles'));
    }

    public function store(Request $request)
    {
        SenderProfile::create($this->validated($request));
        return back()->with('success', 'Profilo di invio creato.');
    }

    public function update(Request $request, SenderProfile $senderProfile)
    {
        $senderProfile->update($this->validated($request));
        return back()->with('success', 'Profilo di invio aggiornato.');
    }

    public function destroy(SenderProfile $senderProfile)
    {
        $senderProfile->delete();
        return back()->with('success', "Profilo «{$senderProfile->name}» eliminato. Le campagne già create mantengono i loro dati mittente.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'from_name'         => 'required|string|max:100',
            'from_email'        => 'required|email',
            'reply_to'          => 'nullable|email',
            'configuration_set' => 'nullable|string|max:100',
        ]);
        $data['configuration_set'] = $data['configuration_set'] ?: null;
        $data['reply_to'] = $data['reply_to'] ?: null;
        return $data;
    }
}
