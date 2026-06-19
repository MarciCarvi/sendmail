<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\SesService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function form(string $token)
    {
        // Do NOT persist on GET — a crawler hitting /embed/{anything} must not
        // create list rows. Render the form against an in-memory list; the row
        // is created only when someone actually subscribes (POST).
        $list = $this->resolveList($token, createIfMissing: false);
        return view('public.subscribe', compact('list', 'token'));
    }

    public function subscribe(Request $request, string $token)
    {
        $list = $this->resolveList($token);

        $request->validate([
            'email'      => 'required|email|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'company'    => 'nullable|string|max:150',
        ]);

        $email = strtolower(trim($request->email));

        // Controllo blacklist
        if (Blacklist::isBlacklisted($email) || Blacklist::isDomainBlocked($email)) {
            return $this->respond($request, $list, 'error',
                'Questo indirizzo non può essere iscritto.');
        }

        // Già iscritto alla lista
        $existing = Subscriber::where('list_id', $list->id)->where('email', $email)->first();

        if ($existing) {
            if ($existing->status === 'subscribed') {
                return $this->respond($request, $list, 'success',
                    'Sei già iscritto a questa lista.');
            }
            // Reiscrizione (es. unsubscribed)
            $existing->update([
                'first_name'    => $request->first_name ?? $existing->first_name,
                'last_name'     => $request->last_name  ?? $existing->last_name,
                'company'       => $request->company    ?? $existing->company,
                'status'        => $list->double_optin ? 'unconfirmed' : 'subscribed',
                'subscribed_at' => now(),
            ]);
            $subscriber = $existing;
        } else {
            $subscriber = Subscriber::create([
                'list_id'    => $list->id,
                'email'      => $email,
                'first_name' => $request->first_name ?? '',
                'last_name'  => $request->last_name  ?? '',
                'company'    => $request->company    ?? '',
                'status'     => $list->double_optin ? 'unconfirmed' : 'subscribed',
            ]);
        }

        if ($list->double_optin) {
            $this->sendConfirmationEmail($list, $subscriber);
            return $this->respond($request, $list, 'confirm',
                'Controlla la tua email per confermare l\'iscrizione.');
        }

        return $this->respond($request, $list, 'success',
            'Iscrizione completata! Grazie.');
    }

    private function resolveList(string $token, bool $createIfMissing = true): MailList
    {
        $list = MailList::where('api_token', $token)->first();

        if (!$list) {
            // Auto-crea la lista dal token (es. "website" → lista "Website")
            $name = ucfirst(str_replace(['-', '_'], ' ', $token));
            $attributes = [
                'api_token'    => $token,
                'name'         => $name,
                'from_name'    => Setting::get('default_from_name', config('app.name')),
                'from_email'   => Setting::get('default_from_email', ''),
                'double_optin' => 0,
            ];

            // On GET (form display) return an unsaved instance so crawlers can't
            // create rows; only a real subscription (POST) persists the list.
            $list = $createIfMissing
                ? MailList::create($attributes)
                : new MailList($attributes);
        }

        return $list;
    }

    private function sendConfirmationEmail(MailList $list, Subscriber $subscriber): void
    {
        try {
            $ses = app(SesService::class);
            $confirmUrl = url('/c/' . $subscriber->token);
            $html = '<p>Ciao ' . e($subscriber->first_name) . ',</p>'
                  . '<p>Clicca il link per confermare la tua iscrizione a <strong>' . e($list->name) . '</strong>:</p>'
                  . '<p><a href="' . $confirmUrl . '">' . $confirmUrl . '</a></p>';
            $text = "Conferma iscrizione: {$confirmUrl}";

            $ses->send(
                to:              $subscriber->email,
                toName:          trim("{$subscriber->first_name} {$subscriber->last_name}"),
                subject:         'Conferma la tua iscrizione a ' . $list->name,
                html:            $html,
                text:            $text,
                fromEmail:       $list->from_email,
                fromName:        $list->from_name,
                replyTo:         $list->reply_to ?? $list->from_email,
                campaignId:      '0',
                subscriberToken: $subscriber->token,
            );
        } catch (\Exception) {
            // Se SES non è configurato, l'iscritto rimane unconfirmed
        }
    }

    private function respond(Request $request, MailList $list, string $outcome, string $message)
    {
        // Risposta AJAX (JS embed o fetch)
        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'outcome' => $outcome,
                'message' => $message,
            ])->header('Access-Control-Allow-Origin', '*');
        }

        // Risposta HTML (form tradizionale o iframe)
        return view('public.subscribe', compact('list'))
            ->with('outcome', $outcome)
            ->with('outcomeMessage', $message);
    }
}
