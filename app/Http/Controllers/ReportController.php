<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignClick;
use App\Models\CampaignOpen;
use App\Models\CampaignSend;
use App\Models\SesEvent;
use App\Services\CampaignSender;
use App\Services\CampaignReportService;
use App\Services\UndeliveredService;
use Illuminate\Http\Request;
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

        // Gli stessi numeri alimentano il rapporto per il cliente (CampaignReportService::kpis)
        $k = app(CampaignReportService::class)->kpis($campaign);
        extract($k, EXTR_SKIP);

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
            'sent', 'delivered', 'deliveryRate', 'failed', 'bounced', 'bouncedPermanent', 'complaints', 'undeliveredCount',
            'uniqueOpens', 'totalOpens', 'uniqueClicks', 'totalClicks',
            'unsubscribed', 'openRate', 'clickRate', 'unsubRate',
            'hourLabels', 'hourData', 'dowLabels', 'dowData',
            'openers', 'clicks'
        ));
    }

    /** Invii accettati da SES di una campagna che non risultano consegnati, con il motivo. */
    public function undelivered(Request $request, Campaign $campaign, UndeliveredService $service)
    {
        SesEvent::reconcileCampaign($campaign->id);

        $base = fn() => CampaignSend::where('campaign_id', $campaign->id)->where('status', 'sent')
            ->where(fn($q) => $q->whereNull('delivered_at')->orWhereNotNull('bounced_at'));

        $query = $base()->with('subscriber')->orderByDesc('bounced_at')->orderBy('sent_at');
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->whereHas('subscriber', fn($q) => $q->where('email', 'like', $term));
        }

        $sends = $query->paginate(100)->withQueryString();
        $events = $this->eventsFor($sends->getCollection()->pluck('message_id')->filter()->all());

        $rows = $sends->getCollection()->map(fn($s) => [
            'email'  => $s->subscriber?->email ?? '—',
            'domain' => $service->domainOf($s->subscriber?->email ?? ''),
            'sent_at' => $s->sent_at,
            'reason' => $service->describe($s, $events->get($s->message_id)),
            'bounced' => (bool) $s->bounced_at,
        ]);

        $grace = $service->graceHours();
        $summary = [
            'Bounce permanenti'                => $base()->where('bounce_type', 'Permanent')->count(),
            'Bounce temporanei/indeterminati'  => $base()->whereNotNull('bounced_at')->where(fn($q) => $q->whereNull('bounce_type')->orWhere('bounce_type', '!=', 'Permanent'))->count(),
            "In attesa (meno di {$grace} ore)" => $base()->whereNull('bounced_at')->where('sent_at', '>', now()->subHours($grace))->count(),
            'Nessun evento di consegna'        => $base()->whereNull('bounced_at')->where('sent_at', '<=', now()->subHours($grace))->count(),
        ];

        return view('reports.undelivered', compact('campaign', 'sends', 'rows', 'summary'));
    }

    public function exportUndelivered(Campaign $campaign, UndeliveredService $service)
    {
        SesEvent::reconcileCampaign($campaign->id);

        return response()->streamDownload(function () use ($campaign, $service) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'dominio', 'inviato_il', 'motivo']);

            CampaignSend::with('subscriber')
                ->where('campaign_id', $campaign->id)->where('status', 'sent')
                ->where(fn($q) => $q->whereNull('delivered_at')->orWhereNotNull('bounced_at'))
                ->orderBy('id')
                ->chunk(500, function ($chunk) use ($out, $service) {
                    $events = $this->eventsFor($chunk->pluck('message_id')->filter()->all());
                    foreach ($chunk as $s) {
                        $email = $s->subscriber?->email ?? '';
                        fputcsv($out, [
                            $this->csvSafe($email),
                            $service->domainOf($email),
                            $s->sent_at?->format('Y-m-d H:i:s'),
                            $this->csvSafe($service->describe($s, $events->get($s->message_id))),
                        ]);
                    }
                });

            fclose($out);
        }, "non-consegnati-campagna-{$campaign->id}.csv", ['Content-Type' => 'text/csv']);
    }

    /** Rapporto per il cliente: pagina stampabile (si salva come PDF dal browser). */
    public function clientReport(Campaign $campaign, CampaignReportService $reports)
    {
        SesEvent::reconcileCampaign($campaign->id);

        return view('reports.summary', [
            'campaign' => $campaign,
            'summary'  => $reports->summary($campaign),
            'brand'    => $reports->brand(),
        ]);
    }

    /** CSV per destinatario (uso tecnico: aggiornamento database ed estrazioni). */
    public function exportRecipients(Campaign $campaign, CampaignReportService $reports)
    {
        SesEvent::reconcileCampaign($campaign->id);

        return response()->streamDownload(function () use ($campaign, $reports) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8: Excel mostra correttamente gli accenti
            fputcsv($out, CampaignReportService::csvHeader(), ';');
            foreach ($reports->recipientRows($campaign) as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, "destinatari-campagna-{$campaign->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cronologia di un indirizzo su tutte le campagne. */
    public function recipient(Request $request, CampaignReportService $reports)
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $timeline = $email !== '' ? $reports->timeline($email) : null;

        return view('reports.recipient', compact('email', 'timeline'));
    }

    /** Eventi di bounce, ritardo e rifiuto dei messaggi indicati, raggruppati per message_id. */
    private function eventsFor(array $messageIds)
    {
        if (empty($messageIds)) {
            return collect();
        }

        return SesEvent::whereIn('message_id', $messageIds)
            ->whereIn('event_type', ['Bounce', 'DeliveryDelay', 'Reject'])
            ->orderBy('id')
            ->get()
            ->groupBy('message_id');
    }

    /** Neutralizza le formule nei CSV (= + - @). */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) ? "'" . $value : $value;
    }
}
