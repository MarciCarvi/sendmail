<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;

class ConfirmController extends Controller
{
    public function __invoke(string $token)
    {
        $subscriber = Subscriber::where('token', $token)->first();

        if (! $subscriber) {
            return view('public.invalid-token', [
                'title'   => 'Link non valido',
                'message' => 'Questo link di conferma non è valido.',
            ]);
        }

        if ($subscriber->status !== 'unconfirmed') {
            return view('public.confirmed', compact('subscriber'));
        }

        $subscriber->update([
            'status'        => 'subscribed',
            'subscribed_at' => now(),
        ]);

        return view('public.confirmed', compact('subscriber'));
    }
}
