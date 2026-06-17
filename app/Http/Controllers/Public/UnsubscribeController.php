<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;

class UnsubscribeController extends Controller
{
    public function show(string $token)
    {
        $subscriber = Subscriber::where('token', $token)->first();

        if (! $subscriber) {
            return view('public.invalid-token', [
                'title'   => 'Link non valido',
                'message' => 'Questo link di disiscrizione non è valido.',
            ]);
        }

        if ($subscriber->status === 'unsubscribed') {
            return view('public.unsubscribed', compact('subscriber'));
        }

        return view('public.unsubscribe', compact('subscriber', 'token'));
    }

    public function confirm(string $token)
    {
        $subscriber = Subscriber::where('token', $token)->first();

        if (! $subscriber) {
            return view('public.invalid-token', [
                'title'   => 'Link non valido',
                'message' => 'Questo link di disiscrizione non è valido.',
            ]);
        }

        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->update([
                'status'           => 'unsubscribed',
                'unsubscribed_at'  => now(),
            ]);
        }

        return view('public.unsubscribed', compact('subscriber'));
    }
}
