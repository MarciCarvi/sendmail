<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CampaignClick;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class TrackClickController extends Controller
{
    public function __invoke(Request $request, int $campaignId, string $token)
    {
        $url = base64_decode($request->query('url', ''));

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        $subscriber = Subscriber::where('token', $token)->first();

        if ($subscriber) {
            CampaignClick::create([
                'campaign_id'   => $campaignId,
                'subscriber_id' => $subscriber->id,
                'original_url'  => $url,
                'clicked_at'    => now(),
                'ip'            => $request->ip(),
            ]);
        }

        return redirect()->away($url);
    }
}
