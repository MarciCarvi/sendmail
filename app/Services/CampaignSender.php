<?php

namespace App\Services;

use App\Http\Controllers\CampaignController;
use App\Models\Blacklist;
use App\Models\SesEvent;
use App\Models\Unsubscribe;
use Illuminate\Support\Facades\Log;
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
        $listIds = $campaign->lists()->where('sm_lists.is_test', false)->pluck('sm_lists.id');

        // Disiscritti per il cliente (dominio del mittente della campagna)
        $unsubscribed = Unsubscribe::suppressedFor($campaign->from_email);

        $subscribers = Subscriber::whereIn('list_id', $listIds)
            ->where('status', 'subscribed')
            ->get()
            ->filter(fn($s) => !Blacklist::isBlacklisted($s->email) && !Blacklist::isDomainBlocked($s->email))
            ->reject(fn($s) => isset($unsubscribed[strtolower($s->email)]))
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
            try {
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
                    configurationSet: $campaign->senderProfile?->configuration_set,
                );

                $send->update([
                    'status'     => $messageId ? 'sent' : 'failed',
                    'sent_at'    => $messageId ? now() : null,
                    'message_id' => $messageId ?: null,
                ]);

                // Se l'evento SES (consegna/bounce) è arrivato prima di questo salvataggio, applicalo ora
                if ($messageId) {
                    try {
                        SesEvent::applyPendingFor($messageId);
                    } catch (\Throwable $e) {
                        Log::warning('Applicazione eventi SES in attesa fallita', ['message_id' => $messageId, 'error' => $e->getMessage()]);
                    }
                }

                $messageId ? $sentCount++ : $failedCount++;
            } catch (\Throwable $e) {
                // Problemi di rete o credenziali: blocca il lotto (il browser riprova con attesa crescente)
                if ($e instanceof \GuzzleHttp\Exception\ConnectException || $e instanceof \Aws\Exception\CredentialsException) {
                    throw $e;
                }

                // Un destinatario "avvelenato" (indirizzo o dati che fanno fallire l'invio) non deve bloccare la campagna
                Log::error('Invio fallito per un destinatario', [
                    'campaign_id'   => $campaign->id,
                    'subscriber_id' => $send->subscriber_id,
                    'error'         => $e->getMessage(),
                ]);
                $send->update(['status' => 'failed']);
                $failedCount++;
            }
        }

        $pending = CampaignSend::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        if ($pending === 0) {
            $campaign->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return $this->snapshot($campaign);
    }

    /**
     * Stato di avanzamento dell'invio (usato da process-batch, progress e dai report).
     */
    public function snapshot(Campaign $campaign): array
    {
        $counts = CampaignSend::where('campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $sent    = (int) ($counts['sent'] ?? 0);
        $failed  = (int) ($counts['failed'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $total   = (int) Campaign::whereKey($campaign->id)->value('total_recipients');

        return [
            'status'  => Campaign::whereKey($campaign->id)->value('status'),
            'pending' => $pending,
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => $total,
            'percent' => $total > 0 ? (int) round(($sent + $failed) / $total * 100) : 0,
        ];
    }

    public function resume(Campaign $campaign): void
    {
        $campaign->update(['status' => 'sending']);
    }
}
