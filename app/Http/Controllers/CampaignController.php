<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\MailList;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\CampaignSender;
use App\Services\SesService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::with('lists')->latest()->get();
        return view('campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $lists = MailList::orderBy('name')->get();
        $defaults = [
            'from_name'  => Setting::get('default_from_name'),
            'from_email' => Setting::get('default_from_email'),
        ];
        return view('campaigns.edit', compact('lists', 'defaults'));
    }

    public function store(Request $request)
    {
        $data = $this->validateDraft($request);
        $campaign = Campaign::create($data);
        $campaign->lists()->sync($request->input('list_ids', []));
        return redirect()->route('campaigns.edit', $campaign)->with('success', 'Campagna creata.');
    }

    public function edit(Campaign $campaign)
    {
        $lists = MailList::orderBy('name')->get();
        $defaults = [];
        return view('campaigns.edit', compact('campaign', 'lists', 'defaults'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        if (!$campaign->isDraft()) {
            return back()->with('error', 'Solo le campagne in bozza possono essere modificate.');
        }
        $data = $this->validateDraft($request);
        $campaign->update($data);
        $campaign->lists()->sync($request->input('list_ids', []));
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

        $stub = new \App\Models\Subscriber([
            'first_name' => 'Mario',
            'last_name'  => 'Rossi',
            'company'    => 'Acme Srl',
            'email'      => $request->test_email,
        ]);
        $html = self::replaceVariables($campaign->html_content ?? '', $stub);
        $text = self::replaceVariables($campaign->text_content ?? '', $stub);

        try {
            $ses = app(SesService::class);
            $ses->send(
                to:              $request->test_email,
                toName:          'Test',
                subject:         '[TEST] ' . $campaign->subject,
                html:            $html,
                text:            $text,
                fromEmail:       $campaign->from_email,
                fromName:        $campaign->from_name,
                replyTo:         $campaign->reply_to ?? $campaign->from_email,
                campaignId:      (string) $campaign->id,
                subscriberToken: 'test',
            );
            return response()->json(['success' => true, 'message' => "Email di test inviata a {$request->test_email}"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function validateDraft(Request $request): array
    {
        return $request->validate([
            'subject'      => 'nullable|string|max:255',
            'from_name'    => 'nullable|string|max:100',
            'from_email'   => 'nullable|email',
            'reply_to'     => 'nullable|email',
            'html_content' => 'nullable|string',
            'design_json'  => 'nullable|string',
            'text_content' => 'nullable|string',
        ]);
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
        if ($campaign->lists()->count() === 0) $errors[] = 'Nessuna lista destinatari selezionata.';
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
