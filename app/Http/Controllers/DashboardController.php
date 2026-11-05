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

        $avgOpenRate = null;
        if ($recentCampaigns->isNotEmpty()) {
            $rates = $recentCampaigns->map(function ($c) {
                $sent   = CampaignSend::where('campaign_id', $c->id)->where('status', 'sent')->count();
                // Tracciamento anonimo: aperture totali ogni 100 inviati (non si sa chi ha aperto)
                $opens = DB::table('sm_campaign_opens')->where('campaign_id', $c->id)->count();
                return $sent > 0 ? round($opens / $sent * 100, 1) : null;
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
