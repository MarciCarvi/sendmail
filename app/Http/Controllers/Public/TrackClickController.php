<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CampaignClick;
use App\Models\CampaignSend;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class TrackClickController extends Controller
{
    public function __invoke(Request $request, int $campaignId, string $token)
    {
        $url = base64_decode($request->query('url', ''), true);

        // Only follow http(s) URLs — blocks redirecting to javascript:, data:,
        // file: and other schemes that turn the tracker into an XSS/phishing gadget.
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            abort(404);
        }

        $subscriber = Subscriber::where('token', $token)->first();

        // Only record the click if this subscriber was actually a recipient of
        // this campaign — prevents stats pollution via arbitrary campaign IDs.
        if ($subscriber && CampaignSend::where('campaign_id', $campaignId)
                ->where('subscriber_id', $subscriber->id)->exists()) {
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
