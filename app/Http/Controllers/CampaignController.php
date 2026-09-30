<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\MailList;
use App\Models\SenderProfile;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\CampaignSender;
use App\Services\SesService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    private const MAX_TEST_RECIPIENTS = 10;

    public function index()
    {
        $campaigns = Campaign::with('lists')->latest()->get();
        return view('campaigns.index', compact('campaigns'));
    }

    public function create(Request $request)
    {
        $editorMode = $request->query('mode') === 'html' ? 'html' : 'visual';
        $lists = MailList::where('is_test', false)->orderBy('name')->get();
        $testLists = MailList::where('is_test', true)->orderBy('name')->get();
        $defaults = [
            'from_name'  => Setting::get('default_from_name'),
            'from_email' => Setting::get('default_from_email'),
        ];
        $profiles = SenderProfile::orderBy('name')->get();
        return view('campaigns.edit', compact('lists', 'testLists', 'defaults', 'profiles', 'editorMode'));
    }

    public function store(Request $request)
    {
        $data = $this->validateDraft($request);
        $campaign = Campaign::create($data);
        $campaign->lists()->sync($this->recipientListIds($request));
        return redirect()->route('campaigns.edit', $campaign)->with('success', 'Campagna creata.');
    }

    public function edit(Campaign $campaign)
    {
        $editorMode = $campaign->editor_mode ?: 'visual';
        $lists = MailList::where('is_test', false)->orderBy('name')->get();
        $testLists = MailList::where('is_test', true)->orderBy('name')->get();
        $defaults = [];
        $profiles = SenderProfile::orderBy('name')->get();
        return view('campaigns.edit', compact('campaign', 'lists', 'testLists', 'defaults', 'profiles', 'editorMode'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        if (!$campaign->isDraft()) {
            return back()->with('error', 'Solo le campagne in bozza possono essere modificate.');
        }
        $data = $this->validateDraft($request);
        unset($data['editor_mode']); // la modalità si sceglie alla creazione e non cambia più
        $campaign->update($data);
        $campaign->lists()->sync($this->recipientListIds($request));
        return back()->with('success', 'Campagna salvata.');
    }

    public function duplicate(Campaign $campaign)
    {
        $copy = $campaign->replicate(['status', 'scheduled_at', 'sent_at', 'total_recipients']);
        $copy->subject = 'Copia di ' . $campaign->subject;
        $copy->status  = 'draft';
        $copy->save();

        $copy->lists()->sync($campaign->lists()->pluck('sm_lists.id'));

        return redirect()->route('campaigns.edit', $copy)->with('success', 'Campagna duplicata.');
    }

    public function destroy(Campaign $campaign)
    {
        if (!$campaign->isDraft()) {
            return back()->with('error', 'Solo le bozze possono essere eliminate.');
        }
        $campaign->delete();
        return redirect()->route('campaigns.index')->with('success', 'Campagna eliminata.');
    }

    public function sendTest(Request $request, Campaign $campaign)
    {
        $request->validate(['test_email' => 'required|email']);

        $stub = new Subscriber([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'company'    => '',
            'email'      => $request->test_email,
        ]);

        try {
            $messageId = $this->deliverTest($campaign, $stub);

            return response()->json($messageId
                ? ['success' => true,  'message' => "Email di test inviata a {$request->test_email}."]
                : ['success' => false, 'message' => "Invio a {$request->test_email} non riuscito. Controlla credenziali SES, mittente verificato e Configuration Set."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Invia il test a tutti i membri di una lista di test (max 10).
     * Non crea record in sm_campaign_sends né iscritti: i test non vengono tracciati.
     */
    public function sendTestToList(Request $request, Campaign $campaign)
    {
        $request->validate([
            'test_list_id' => ['required', \Illuminate\Validation\Rule::exists('sm_lists', 'id')->where('is_test', 1)],
        ]);

        // Ignora lo status (chi si è disiscritto per distrazione continua a ricevere i test);
        // esclude solo bounce/complaint per proteggere la reputazione SES e la blacklist.
        $recipients = Subscriber::where('list_id', $request->test_list_id)
            ->whereNotIn('status', ['bounced', 'complained'])
            ->orderBy('email')
            ->get()
            ->filter(fn($s) => !\App\Models\Blacklist::isBlacklisted($s->email)
                            && !\App\Models\Blacklist::isDomainBlocked($s->email))
            ->values();

        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'La lista di test non ha destinatari validi.']);
        }

        if ($recipients->count() > self::MAX_TEST_RECIPIENTS) {
            return response()->json(['success' => false, 'message' => 'La lista di test ha ' . $recipients->count()
                . ' destinatari: il massimo è ' . self::MAX_TEST_RECIPIENTS . '. Riduci la lista.']);
        }

        $sent = 0;
        $failed = [];
        foreach ($recipients as $subscriber) {
            try {
                if ($this->deliverTest($campaign, $subscriber)) {
                    $sent++;
                } else {
                    $failed[] = $subscriber->email;
                }
            } catch (\Exception) {
                $failed[] = $subscriber->email;
            }
        }

        $message = "Test inviato a {$sent} destinatari su {$recipients->count()}.";
        if ($failed) {
            $message .= ' Non riusciti: ' . implode(', ', $failed) . '.';
        }

        return response()->json(['success' => $sent > 0 && !$failed, 'message' => $message]);
    }

    /** Invia una singola email di test (senza tracking e senza righe in sm_campaign_sends). */
    private function deliverTest(Campaign $campaign, Subscriber $subscriber): string|false
    {
        return app(SesService::class)->send(
            to:               $subscriber->email,
            toName:           trim("{$subscriber->first_name} {$subscriber->last_name}") ?: 'Test',
            subject:          '[TEST] ' . self::replaceVariables($campaign->subject ?? '', $subscriber),
            html:             self::replaceVariables($campaign->html_content ?? '', $subscriber),
            text:             self::replaceVariables($campaign->text_content ?? '', $subscriber),
            fromEmail:        $campaign->from_email,
            fromName:         $campaign->from_name,
            replyTo:          $campaign->reply_to ?: $campaign->from_email,
            campaignId:       (string) $campaign->id,
            subscriberToken:  $subscriber->token ?? 'test',
            configurationSet: $campaign->senderProfile?->configuration_set,
        );
    }

    /** ID delle liste destinatarie scelte, escluse le liste di test. */
    private function recipientListIds(Request $request): array
    {
        return MailList::whereIn('id', (array) $request->input('list_ids', []))
            ->where('is_test', false)
            ->pluck('id')
            ->all();
    }

    private function validateDraft(Request $request): array
    {
        $data = $request->validate([
            'subject'      => 'nullable|string|max:255',
            'from_name'    => 'nullable|string|max:100',
            'from_email'   => 'nullable|email',
            'reply_to'     => 'nullable|email',
            'sender_profile_id' => 'nullable|exists:sm_sender_profiles,id',
            'editor_mode'  => 'nullable|in:visual,html',
            'html_content' => 'nullable|string',
            'design_json'  => 'nullable|string',
            'text_content' => 'nullable|string',
        ]);

        // Le colonne sono NOT NULL, ma una bozza può avere questi campi vuoti
        // (ConvertEmptyStringsToNull li trasforma in null): salva stringa vuota.
        foreach (['subject', 'from_name', 'from_email'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }
        $data['editor_mode'] = $data['editor_mode'] ?? 'visual';

        return $data;
    }

    public function sendNow(Campaign $campaign)
    {
        $errors = $this->validateForSend($campaign);
        if ($errors) {
            return back()->with('error', implode(' ', $errors));
        }

        app(CampaignSender::class)->prepare($campaign);

        return back()->with('success', 'Invio avviato.');
    }

    public function processBatch(Campaign $campaign)
    {
        if (!$campaign->isSending()) {
            return response()->json(['status' => $campaign->status, 'pending' => 0]);
        }

        $result = app(CampaignSender::class)->processBatch($campaign, app(SesService::class));

        return response()->json($result);
    }

    public function schedule(Request $request, Campaign $campaign)
    {
        $errors = $this->validateForSend($campaign);
        if ($errors) {
            return back()->with('error', implode(' ', $errors));
        }

        $request->validate(['scheduled_at' => 'required|date|after:now']);

        $campaign->update([
            'status'       => 'scheduled',
            'scheduled_at' => $request->scheduled_at,
        ]);

        return back()->with('success', 'Campagna programmata per il ' . \Carbon\Carbon::parse($request->scheduled_at)->format('d/m/Y H:i') . '.');
    }

    public function pause(Campaign $campaign)
    {
        if (!$campaign->isSending()) {
            return back()->with('error', 'La campagna non è in invio.');
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Invio messo in pausa. I job già in coda verranno scartati automaticamente.');
    }

    public function resume(Campaign $campaign)
    {
        if (!$campaign->isPaused()) {
            return back()->with('error', 'La campagna non è in pausa.');
        }

        app(CampaignSender::class)->resume($campaign);

        return back()->with('success', 'Invio ripreso.');
    }

    public function progress(Campaign $campaign)
    {
        $total   = $campaign->total_recipients ?: 1;
        $sent    = CampaignSend::where('campaign_id', $campaign->id)->where('status', 'sent')->count();
        $failed  = CampaignSend::where('campaign_id', $campaign->id)->where('status', 'failed')->count();
        $pending = CampaignSend::where('campaign_id', $campaign->id)->where('status', 'pending')->count();

        return response()->json([
            'status'   => $campaign->fresh()->status,
            'total'    => $campaign->total_recipients,
            'sent'     => $sent,
            'failed'   => $failed,
            'pending'  => $pending,
            'percent'  => $total > 0 ? round(($sent + $failed) / $total * 100) : 0,
        ]);
    }

    public function validateForSend(Campaign $campaign): array
    {
        $errors = [];
        if (empty($campaign->subject))    $errors[] = 'Oggetto mancante.';
        if (empty($campaign->from_name))  $errors[] = 'Nome mittente mancante.';
        if (empty($campaign->from_email)) $errors[] = 'Email mittente mancante.';
        if ($campaign->lists()->where('sm_lists.is_test', false)->count() === 0) $errors[] = 'Nessuna lista destinatari selezionata.';
        return $errors;
    }

    public static function replaceVariables(string $content, Subscriber $subscriber): string
    {
        $fullName       = trim("{$subscriber->first_name} {$subscriber->last_name}");
        $unsubscribeUrl = url('/u/' . $subscriber->token);

        return str_replace(
            ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{company}}', '{{email}}', '{{unsubscribe_url}}'],
            [$subscriber->first_name, $subscriber->last_name, $fullName, $subscriber->company, $subscriber->email, $unsubscribeUrl],
            $content
        );
    }
}
