<?php

namespace App\Http\Controllers;

use App\Models\AddressStatus;
use Illuminate\Http\Request;

class AddressStatusController extends Controller
{
    /** Riabilita un indirizzo ovunque (da «Cerca destinatario»): vale anche se non esistono più righe iscritto. */
    public function reinstate(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $email = AddressStatus::normalize($data['email']);

        $rows = AddressStatus::reinstate($email);

        return redirect()->route('reports.recipient', ['email' => $email])
            ->with('success', "«{$email}» riabilitato: {$rows} iscrizioni di nuovo attive.");
    }
}
