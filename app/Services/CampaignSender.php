<?php

namespace App\Services;

use App\Http\Controllers\CampaignController;
use App\Models\Blacklist;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\TrackingService;

class CampaignSender
{
    /**
     * Prepara i record di invio e imposta la campagna su "sending".
     * L'invio reale avviene in batch via processBatch() guidato dal browser.
     */
    public function prepare(Campaign $campaign): void
    {
        $listIds = $campaign->lists()->pluck('sm_lists.id');

        $subscribers = Subscriber::whereIn('list_id', $listIds)
            ->where('status', 'subscribed')
            ->get()
            ->filter(fn($s) => !Blacklist::isBlacklisted($s->email) && !Blacklist::isDomainBlocked($s->email))
            ->unique('email')
            ->values();

        // Pulisce eventuali pending precedenti (es. dopo una pausa)
        CampaignSend::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->delete();

        foreach ($subscribers as $subscriber) {
            CampaignSend::create([
                'campaign_id'   => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'status'        => 'pending',
            ]);
        }

        $campaign->update([
            'status'           => 'sending',
            'total_recipients' => $subscribers->count(),
            'sent_at'          => null,
        ]);
    }

    /**
     * Invia un batch di email in modo sincrono.
     * Chiamato ripetutamente dal browser via AJAX fino a esaurimento dei pending.
     */
    public function processBatch(Campaign $campaign, SesService $ses): array
    {
        $rate      = max(1, (int) Setting::get('ses_sending_rate', 14));
        $batchSize = min($rate, 10);
        $tracking  = app(TrackingService::class);

        $sends = CampaignSend::with('subscriber')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->limit($batchSize)
            ->get();

        $sentCount   = 0;
        $failedCount = 0;

        foreach ($sends as $send) {
            $subscriber = $send->subscriber;

            if (!$subscriber) {
                $send->update(['status' => 'failed']);
                $failedCount++;
                continue;
            }

            $html = CampaignController::replaceVariables($campaign->html_content ?? '', $subscriber);
            $html = $tracking->injectTracking($html, $campaign->id, $subscriber->token);
            $text = CampaignController::replaceVariables($campaign->text_content ?? '', $subscriber);

            $messageId = $ses->send(
                to:              $subscriber->email,
                toName:          trim("{$subscriber->first_name} {$subscriber->last_name}"),
                subject:         CampaignController::replaceVariables($campaign->subject ?? '', $subscriber),
                html:            $html,
                text:            $text,
                fromEmail:       $campaign->from_email,
                fromName:        $campaign->from_name,
                replyTo:         $campaign->reply_to ?? $campaign->from_email,
                campaignId:      (string) $campaign->id,
                subscriberToken: $subscriber->token,
            );

            $send->update([
                'status'     => $messageId ? 'sent' : 'failed',
                'sent_at'    => $messageId ? now() : null,
                'message_id' => $messageId ?: null,
            ]);

            $ok ? $sentCount++ : $failedCount++;
        }

        $pending = CampaignSend::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        if ($pending === 0) {
            $campaign->update(['status' => 'sent', 'sent_at' => now()]);
        }

        $total  = $campaign->total_recipients ?: 1;
        $done   = CampaignSend::where('campaign_id', $campaign->id)->whereIn('status', ['sent', 'failed'])->count();

        return [
            'status'  => $campaign->fresh()->status,
            'pending' => $pending,
            'sent'    => CampaignSend::where('campaign_id', $campaign->id)->where('status', 'sent')->count(),
            'failed'  => CampaignSend::where('campaign_id', $campaign->id)->where('status', 'failed')->count(),
            'total'   => $campaign->total_recipients,
            'percent' => (int) round($done / $total * 100),
        ];
    }

    public function resume(Campaign $campaign): void
    {
        $campaign->update(['status' => 'sending']);
    }
}
