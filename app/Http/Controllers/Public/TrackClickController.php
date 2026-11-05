<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignClick;
use App\Models\CampaignSend;
use App\Models\Subscriber;
use App\Services\TrackingService;
use Illuminate\Http\Request;

/**
 * Click su link ANONIMO: registra che il link della campagna è stato cliccato, in un'ora. Non salva chi (nessun
 * iscritto) né l'indirizzo IP. Il redirect è consentito solo agli indirizzi firmati nell'email (nessun redirect
 * aperto); le email vecchie (fino alla 1.13.x) continuano a funzionare.
 */
class TrackClickController extends Controller
{
    public function __invoke(Request $request, TrackingService $tracking, int $campaignId, string $token)
    {
        $url = base64_decode($request->query('url', ''), true);

        // Only follow http(s) URLs — blocks redirecting to javascript:, data:,
        // file: and other schemes that turn the tracker into an XSS/phishing gadget.
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            abort(404);
        }

        $legacy = $tracking->isLegacyToken($token);
        $valid  = $tracking->isValidClick($campaignId, $token, $url);

        // Nuovi indirizzi: se la firma non torna non si fa da redirect. I vecchi (token dell'iscritto) restano
        // funzionanti come prima, perché sono già nelle email inviate.
        if (!$valid && !$legacy) {
            abort(404);
        }

        if (!$valid && $legacy) {
            $valid = $this->isLegacyRecipient($campaignId, $token);
        }

        if ($valid && Campaign::whereKey($campaignId)->exists()) {
            CampaignClick::create([
                'campaign_id'   => $campaignId,
                'subscriber_id' => null,
                'original_url'  => $url,
                'clicked_at'    => now()->startOfHour(),
                'ip'            => null,
            ]);
        }

        return redirect()->away($url);
    }

    /** Solo per verificare che sia un destinatario vero della campagna: l'identità NON viene salvata. */
    private function isLegacyRecipient(int $campaignId, string $token): bool
    {
        $subscriber = Subscriber::where('token', $token)->first();

        return $subscriber && CampaignSend::where('campaign_id', $campaignId)
            ->where('subscriber_id', $subscriber->id)->exists();
    }
}
