<?php

namespace App\Jobs;

use App\Http\Controllers\CampaignController;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Subscriber;
use App\Services\SesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public readonly int $sendId)
    {
    }

    public function handle(SesService $ses): void
    {
        $send = CampaignSend::find($this->sendId);
        if (!$send || $send->status !== 'pending') {
            return;
        }

        $campaign = Campaign::find($send->campaign_id);
        if (!$campaign || !$campaign->isSending()) {
            return;
        }

        $subscriber = Subscriber::find($send->subscriber_id);
        if (!$subscriber) {
            $send->update(['status' => 'failed']);
            return;
        }

        $html = CampaignController::replaceVariables($campaign->html_content ?? '', $subscriber);
        $text = CampaignController::replaceVariables($campaign->text_content ?? '', $subscriber);

        $ok = $ses->send(
            to:              $subscriber->email,
            toName:          trim("{$subscriber->first_name} {$subscriber->last_name}"),
            subject:         CampaignController::replaceVariables($campaign->subject, $subscriber),
            html:            $html,
            text:            $text,
            fromEmail:       $campaign->from_email,
            fromName:        $campaign->from_name,
            replyTo:         $campaign->reply_to ?? $campaign->from_email,
            campaignId:      (string) $campaign->id,
            subscriberToken: $subscriber->token,
        );

        $send->update([
            'status'  => $ok ? 'sent' : 'failed',
            'sent_at' => $ok ? now() : null,
        ]);

        // Se non rimangono più invii pending, segna la campagna come completata
        $stillPending = CampaignSend::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->exists();

        if (!$stillPending) {
            $campaign->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        CampaignSend::where('id', $this->sendId)->update(['status' => 'failed']);
    }
}
