<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignClick;
use App\Models\CampaignOpen;
use App\Models\CampaignSend;
use App\Models\SesEvent;
use App\Services\CampaignSender;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::where('status', 'sent')
            ->orderByDesc('sent_at')
            ->get()
            ->map(function ($c) {
                $sent          = CampaignSend::where('campaign_id', $c->id)->where('status', 'sent')->count();
                $uniqueOpens   = CampaignOpen::where('campaign_id', $c->id)->distinct('subscriber_id')->count('subscriber_id');
                $uniqueClicks  = CampaignClick::where('campaign_id', $c->id)->distinct('subscriber_id')->count('subscriber_id');
                $c->stat_sent        = $sent;
                $c->stat_open_rate   = $sent > 0 ? round($uniqueOpens  / $sent * 100, 1) : 0;
                $c->stat_click_rate  = $sent > 0 ? round($uniqueClicks / $sent * 100, 1) : 0;
                return $c;
            });

        // campagne in invio o in pausa: non sono ancora tra le "inviate" ma si possono già seguire
        $inProgress = Campaign::whereIn('status', ['sending', 'paused'])
            ->orderByDesc('updated_at')
            ->get()
            ->each(fn($c) => $c->progress = app(CampaignSender::class)->snapshot($c));

        return view('reports.index', compact('campaigns', 'inProgress'));
    }

    public function show(Campaign $campaign)
    {
        $progress = in_array($campaign->status, ['sending', 'paused'], true)
            ? app(CampaignSender::class)->snapshot($campaign)
            : null;

        // Applica eventuali eventi SES arrivati prima che l'invio salvasse il message_id
        SesEvent::reconcileCampaign($campaign->id);

        $sends     = fn() => CampaignSend::where('campaign_id', $campaign->id);
        $sent      = $sends()->where('status', 'sent')->count();
        // consegnati = consegna confermata e nessun bounce successivo (i bounce asincroni arrivano dopo la consegna)
        $delivered = $sends()->whereNotNull('delivered_at')->whereNull('bounced_at')->count();
        $failed    = $sends()->where('status', 'failed')->count();
        $bounced   = $sends()->whereNotNull('bounced_at')->count();
        $bouncedPermanent = $sends()->where('bounce_type', 'Permanent')->count();
        $complaints = $sends()->whereNotNull('complained_at')->count();

        $uniqueOpens  = CampaignOpen::where('campaign_id', $campaign->id)->distinct('subscriber_id')->count('subscriber_id');
        $totalOpens   = CampaignOpen::where('campaign_id', $campaign->id)->count();
        $uniqueClicks = CampaignClick::where('campaign_id', $campaign->id)->distinct('subscriber_id')->count('subscriber_id');
        $totalClicks  = CampaignClick::where('campaign_id', $campaign->id)->count();

        $unsubscribed = \App\Models\Subscriber::whereHas('sends', function ($q) use ($campaign) {
            $q->where('campaign_id', $campaign->id)->where('status', 'sent');
        })->where('status', 'unsubscribed')->count();

        $deliveryRate = $sent > 0 ? round($delivered   / $sent * 100, 1) : 0;
        $openRate     = $sent > 0 ? round($uniqueOpens  / $sent * 100, 1) : 0;
        $clickRate    = $sent > 0 ? round($uniqueClicks / $sent * 100, 1) : 0;
        $unsubRate    = $sent > 0 ? round($unsubscribed / $sent * 100, 1) : 0;

        // Aperture per ora del giorno (aggregato su tutti i giorni)
        $opensByHour = CampaignOpen::where('campaign_id', $campaign->id)
            ->select(DB::raw('HOUR(opened_at) as hour, COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        // Costruisce array 0-23 riempiendo gli slot vuoti con 0
        $hourLabels = [];
        $hourData   = [];
        for ($h = 0; $h < 24; $h++) {
            $hourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourData[]   = $opensByHour->get($h)?->count ?? 0;
        }

        // Aperture per giorno della settimana
        $days = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
        $opensByDow = CampaignOpen::where('campaign_id', $campaign->id)
            ->select(DB::raw('DAYOFWEEK(opened_at) as dow, COUNT(*) as count'))
            ->groupBy('dow')
            ->orderBy('dow')
            ->get()
            ->keyBy('dow');

        $dowLabels = [];
        $dowData   = [];
        for ($d = 1; $d <= 7; $d++) {
            $dowLabels[] = $days[$d - 1];
            $dowData[]   = $opensByDow->get($d)?->count ?? 0;
        }

        // Ultimi 50 che hanno aperto
        $openers = CampaignOpen::with('subscriber')
            ->where('campaign_id', $campaign->id)
            ->latest('opened_at')
            ->limit(50)
            ->get();

        // Ultimi 50 click
        $clicks = CampaignClick::with('subscriber')
            ->where('campaign_id', $campaign->id)
            ->latest('clicked_at')
            ->limit(50)
            ->get();

        return view('reports.show', compact(
            'campaign', 'progress',
            'sent', 'delivered', 'deliveryRate', 'failed', 'bounced', 'bouncedPermanent', 'complaints',
            'uniqueOpens', 'totalOpens', 'uniqueClicks', 'totalClicks',
            'unsubscribed', 'openRate', 'clickRate', 'unsubRate',
            'hourLabels', 'hourData', 'dowLabels', 'dowData',
            'openers', 'clicks'
        ));
    }
}
