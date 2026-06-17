<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SesWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload) {
            return response('Bad Request', 400);
        }

        $type = $payload['Type'] ?? '';

        // Conferma sottoscrizione SNS
        if ($type === 'SubscriptionConfirmation') {
            Http::get($payload['SubscribeURL']);
            return response('OK');
        }

        if ($type !== 'Notification') {
            return response('OK');
        }

        $message = json_decode($payload['Message'] ?? '{}', true);
        $notifType = $message['notificationType'] ?? '';

        if ($notifType === 'Bounce') {
            $bounceType = $message['bounce']['bounceType'] ?? '';
            if ($bounceType === 'Permanent') {
                foreach ($message['bounce']['bouncedRecipients'] ?? [] as $recipient) {
                    Subscriber::where('email', strtolower($recipient['emailAddress']))
                        ->where('status', 'subscribed')
                        ->update(['status' => 'bounced']);
                }
            }
        }

        if ($notifType === 'Complaint') {
            foreach ($message['complaint']['complainedRecipients'] ?? [] as $recipient) {
                Subscriber::where('email', strtolower($recipient['emailAddress']))
                    ->where('status', 'subscribed')
                    ->update(['status' => 'complained']);
            }
        }

        if ($notifType === 'Delivery') {
            $messageId = $message['mail']['messageId'] ?? null;
            if ($messageId) {
                \App\Models\CampaignSend::where('message_id', $messageId)
                    ->whereNull('delivered_at')
                    ->update(['delivered_at' => now()]);
            }
        }

        return response('OK');
    }
}
