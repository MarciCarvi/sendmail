<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CampaignSend;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SesWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            return response('Bad Request', 400);
        }

        // Reject anything whose SNS signature does not validate. This is what
        // stops attackers from forging bounce/complaint/delivery notifications
        // or triggering SSRF via a fake SubscriptionConfirmation.
        if (!$this->isValidSnsMessage($payload)) {
            Log::warning('SES webhook: invalid SNS signature, rejected.');
            return response('Forbidden', 403);
        }

        $type = $payload['Type'] ?? '';

        // Conferma sottoscrizione SNS — solo verso host SNS legittimi
        if ($type === 'SubscriptionConfirmation') {
            $subscribeUrl = $payload['SubscribeURL'] ?? '';
            if ($this->isAllowedSnsUrl($subscribeUrl)) {
                Http::timeout(10)->get($subscribeUrl);
            }
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
                CampaignSend::where('message_id', $messageId)
                    ->whereNull('delivered_at')
                    ->update(['delivered_at' => now()]);
            }
        }

        return response('OK');
    }

    /**
     * Verify an Amazon SNS message signature per the documented algorithm.
     * https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
     */
    private function isValidSnsMessage(array $payload): bool
    {
        $signature  = $payload['Signature'] ?? null;
        $certUrl    = $payload['SigningCertURL'] ?? ($payload['SigningCertUrl'] ?? null);
        $sigVersion = $payload['SignatureVersion'] ?? '1';

        if (!$signature || !$certUrl || !$this->isAllowedSnsUrl($certUrl)) {
            return false;
        }

        $stringToSign = $this->buildStringToSign($payload);
        if ($stringToSign === null) {
            return false;
        }

        $publicKey = $this->fetchPublicKey($certUrl);
        if (!$publicKey) {
            return false;
        }

        $algo = $sigVersion === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

        return openssl_verify(
            $stringToSign,
            base64_decode($signature),
            $publicKey,
            $algo
        ) === 1;
    }

    /**
     * Build the canonical string-to-sign from the exact fields and order SNS
     * requires for each message type.
     */
    private function buildStringToSign(array $payload): ?string
    {
        $type = $payload['Type'] ?? '';

        $fields = match ($type) {
            'Notification' => isset($payload['Subject'])
                ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
                : ['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' =>
                ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default => null,
        };

        if ($fields === null) {
            return null;
        }

        $string = '';
        foreach ($fields as $field) {
            if (!isset($payload[$field])) {
                return null;
            }
            $string .= $field . "\n" . $payload[$field] . "\n";
        }

        return $string;
    }

    /**
     * Fetch and cache the SNS signing certificate's public key.
     */
    private function fetchPublicKey(string $certUrl): mixed
    {
        $cacheKey = 'sns_cert_' . md5($certUrl);

        $pem = Cache::remember($cacheKey, now()->addHours(24), function () use ($certUrl) {
            $response = Http::timeout(10)->get($certUrl);
            return $response->successful() ? $response->body() : null;
        });

        if (!$pem) {
            Cache::forget($cacheKey);
            return false;
        }

        return openssl_pkey_get_public($pem) ?: false;
    }

    /**
     * Only allow URLs hosted on Amazon SNS over HTTPS — blocks SSRF and
     * certificate-spoofing via attacker-controlled hosts.
     */
    private function isAllowedSnsUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        return (bool) preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/', $parts['host']);
    }
}
