<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignOpen;
use App\Models\CampaignSend;
use App\Models\Subscriber;
use App\Services\TrackingService;
use Illuminate\Http\Request;

/**
 * Pixel di apertura ANONIMO: registra solo che la campagna è stata aperta, in un'ora. Non salva chi (nessun iscritto),
 * né indirizzo IP, né user agent. L'orario è troncato all'ora.
 */
class TrackOpenController extends Controller
{
    public function __invoke(Request $request, TrackingService $tracking, int $campaignId, string $token)
    {
        $valid = $tracking->isValidOpen($campaignId, $token) || $this->isLegacyRecipient($tracking, $campaignId, $token);

        // Un conteggio per apertura, solo per campagne esistenti (evita statistiche inquinate da id arbitrari)
        if ($valid && Campaign::whereKey($campaignId)->exists()) {
            CampaignOpen::create([
                'campaign_id'   => $campaignId,
                'subscriber_id' => null,
                'opened_at'     => now()->startOfHour(),
                'ip'            => null,
                'user_agent'    => null,
            ]);
        }

        return response(base64_decode(TrackingService::PIXEL_GIF), 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Email inviate fino alla 1.13.x: il pixel contiene il token dell'iscritto. Si usa solo per verificare che sia
     * un destinatario vero della campagna; l'identità NON viene salvata.
     */
    private function isLegacyRecipient(TrackingService $tracking, int $campaignId, string $token): bool
    {
        if (!$tracking->isLegacyToken($token)) {
            return false;
        }

        $subscriber = Subscriber::where('token', $token)->first();

        return $subscriber && CampaignSend::where('campaign_id', $campaignId)
            ->where('subscriber_id', $subscriber->id)->exists();
    }
}
