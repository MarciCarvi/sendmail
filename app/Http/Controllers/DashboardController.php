<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Services\LicenseService;
use App\Services\UpdateService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(LicenseService $license, UpdateService $updates)
    {
        $lists      = MailList::count();
        $subscribers = Subscriber::where('status', 'subscribed')->count();
        $campaigns  = Campaign::where('status', 'sent')->count();
        $sent24h    = CampaignSend::where('status', 'sent')
                        ->where('sent_at', '>=', now()->subHours(24))
                        ->count();

        $recentCampaigns = Campaign::where('status', 'sent')
            ->orderByDesc('sent_at')
            ->limit(5)
            ->get();

        $openRateAvg = null;
        if ($campaigns > 0) {
            $openRateAvg = DB::table('sm_campaign_opens')
                ->join('sm_campaign_sends', function ($j) {
                    $j->on('sm_campaign_opens.campaign_id', '=', 'sm_campaign_sends.campaign_id')
                      ->on('sm_campaign_opens.subscriber_id', '=', 'sm_campaign_sends.subscriber_id');
                })
                ->where('sm_campaign_sends.status', 'sent')
                ->select('sm_campaign_opens.campaign_id')
                ->distinct()
                ->count();
        }

        $avgOpenRate = null;
        if ($recentCampaigns->isNotEmpty()) {
            $rates = $recentCampaigns->map(function ($c) {
                $sent   = CampaignSend::where('campaign_id', $c->id)->where('status', 'sent')->count();
                $opened = DB::table('sm_campaign_opens')->where('campaign_id', $c->id)
                            ->distinct('subscriber_id')->count('subscriber_id');
                return $sent > 0 ? round($opened / $sent * 100, 1) : null;
            })->filter()->values();

            $avgOpenRate = $rates->isNotEmpty()
                ? round($rates->avg(), 1)
                : null;
        }

        $newSubscribers = Subscriber::where('status', 'subscribed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $licenseStatus = $license->check();
        $updateInfo    = $updates->checkForUpdates();

        return view('dashboard', compact(
            'lists', 'subscribers', 'campaigns', 'sent24h',
            'recentCampaigns', 'avgOpenRate', 'newSubscribers',
            'licenseStatus', 'updateInfo'
        ));
    }
}
