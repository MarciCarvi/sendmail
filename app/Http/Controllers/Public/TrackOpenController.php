<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CampaignOpen;
use App\Models\Subscriber;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackOpenController extends Controller
{
    public function __invoke(Request $request, int $campaignId, string $token)
    {
        $subscriber = Subscriber::where('token', $token)->first();

        if ($subscriber) {
            CampaignOpen::create([
                'campaign_id'   => $campaignId,
                'subscriber_id' => $subscriber->id,
                'opened_at'     => now(),
                'ip'            => $request->ip(),
                'user_agent'    => $request->userAgent(),
            ]);
        }

        return response(base64_decode(TrackingService::PIXEL_GIF), 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
