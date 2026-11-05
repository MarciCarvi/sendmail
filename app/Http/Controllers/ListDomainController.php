<?php

namespace App\Http\Controllers;

use App\Models\DomainCheck;
use App\Models\MailList;
use App\Services\DomainCheckService;
use App\Services\UndeliveredService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Controllo DNS dei domini degli iscritti di una singola lista: elenca i domini «problematici» (non risolvono, non
 * ricevono posta, senza MX) con il numero di utenti in lista, da verificare uno per uno. Si lancia a mano quando si vuole.
 */
class ListDomainController extends Controller
{
    private const PER_PAGE = 50;
    private const BATCH_MAX = 12;       // domini per richiesta
    private const BATCH_SECONDS = 20;   // tempo massimo per richiesta

    public function index(Request $request, MailList $list, DomainCheckService $svc, UndeliveredService $undelivered)
    {
        $rows = $this->rows($list, $svc);

        $problems     = $rows->filter(fn($r) => $r['check'] && in_array($r['check']->status, DomainCheckService::PROBLEMS, true));
        $summary = [
            'domains'        => $rows->count(),
            'users'          => $rows->sum('users'),
            'unchecked'      => $rows->filter(fn($r) => !$r['check'])->count(),
            'ok'             => $rows->filter(fn($r) => $r['check']?->status === DomainCheckService::OK)->count(),
            'problems'       => $problems->count(),
            'problem_users'  => $problems->sum('users'),
            'problem_active' => $problems->sum('active'),
            'last_check'     => $rows->map(fn($r) => $r['check']?->checked_at)->filter()->max(),
        ];
        $byStatus = $problems->groupBy(fn($r) => $r['check']->status)->map->count();

        // Filtri: tipo di problema e ricerca per dominio
        $filtered = $problems;
        if (in_array($request->status, DomainCheckService::PROBLEMS, true)) {
            $filtered = $filtered->filter(fn($r) => $r['check']->status === $request->status);
        }
        if ($request->filled('search')) {
            $needle = strtolower(trim((string) $request->search));
            $filtered = $filtered->filter(fn($r) => str_contains($r['domain'], $needle));
        }

        $filtered = $this->sorted($filtered);

        $page    = max(1, (int) $request->query('page', 1));
        $entries = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values(),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $suggestions = $entries->getCollection()
            ->mapWithKeys(fn($r) => [$r['domain'] => $undelivered->suggestDomain($r['domain'])])
            ->filter()->all();

        return view('lists.domains', compact('list', 'entries', 'summary', 'byStatus', 'suggestions'));
    }

    /** Un gruppo di domini; il browser lo ripete finché ne restano. */
    public function check(Request $request, MailList $list, DomainCheckService $svc)
    {
        $data = $request->validate([
            'pass'  => 'nullable|string|max:40',
            'scope' => 'nullable|in:all,problems',
        ]);

        $pass  = !empty($data['pass'])
            ? Carbon::parse($data['pass'])->setTimezone(config('app.timezone'))
            : now()->startOfSecond();
        $scope = $data['scope'] ?? 'all';

        if (!$svc->dnsAvailable()) {
            return response()->json([
                'error' => 'Il server non riesce a interrogare il DNS (o lo vieta): il controllo non è affidabile, quindi non è stato eseguito.',
            ], 422);
        }

        $pending = $this->pending($list, $svc, $pass, $scope);

        $started = microtime(true);
        $checked = 0;
        foreach ($pending as $domain) {
            if ($checked >= self::BATCH_MAX || (microtime(true) - $started) > self::BATCH_SECONDS) {
                break;
            }
            $svc->record($domain, $svc->inspect($domain));
            $checked++;
        }

        return response()->json([
            'pass'      => $pass->toIso8601String(),
            'checked'   => $checked,
            'remaining' => $pending->count() - $checked,
        ]);
    }

    /** Ricontrolla un solo dominio della lista. */
    public function recheck(Request $request, MailList $list, DomainCheckService $svc)
    {
        $data   = $request->validate(['domain' => 'required|string|max:255']);
        $domain = strtolower(trim($data['domain']));

        if (!$svc->listDomains($list)->contains('domain', $domain)) {
            return back()->withErrors(['domain' => 'Dominio non presente in questa lista.']);
        }

        if (!$svc->dnsAvailable()) {
            return back()->withErrors(['domain' => 'Il server non riesce a interrogare il DNS: controllo non eseguito.']);
        }

        $result = $svc->record($domain, $svc->inspect($domain));
        [, $label] = DomainCheckService::label($result->status);

        return back()->with('success', "«{$domain}» ricontrollato: {$label}. {$result->detail}");
    }

    /** CSV dei domini problematici (`;` + BOM, orari di Roma). */
    public function export(MailList $list, DomainCheckService $svc)
    {
        $tz   = config('sendmail.report_timezone', 'Europe/Rome');
        $rows = $this->sorted($this->rows($list, $svc)->filter(
            fn($r) => $r['check'] && in_array($r['check']->status, DomainCheckService::PROBLEMS, true)
        ));

        return response()->streamDownload(function () use ($rows, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8: Excel mostra correttamente gli accenti
            fputcsv($out, ['Dominio', 'Problema', 'Dettaglio', 'Utenti in lista', 'Di cui iscritti', 'Ultimo controllo'], ';', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $this->csvSafe($r['domain']),
                    DomainCheckService::label($r['check']->status)[1],
                    $this->csvSafe($r['check']->detail),
                    $r['users'],
                    $r['active'],
                    $r['check']->checked_at?->copy()->setTimezone($tz)->format('d/m/Y H:i'),
                ], ';', '"', '\\');
            }
            fclose($out);
        }, 'domini-problematici-lista-' . $list->id . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Domini della lista con utenti ed esito dell'ultimo controllo (null se mai controllato). */
    private function rows(MailList $list, DomainCheckService $svc)
    {
        $domains = $svc->listDomains($list);
        $checks  = $svc->checksFor($domains->pluck('domain')->all());

        return $domains->map(fn($d) => [
            'domain' => $d->domain,
            'users'  => $d->users,
            'active' => $d->active,
            'check'  => $checks->get($d->domain),
        ]);
    }

    /** Dal più grave, poi dal dominio con più utenti. */
    private function sorted($rows)
    {
        return $rows->sort(function ($a, $b) {
            return [DomainCheckService::severity($a['check']->status), -$a['users'], $a['domain']]
               <=> [DomainCheckService::severity($b['check']->status), -$b['users'], $b['domain']];
        })->values();
    }

    /** Domini ancora da controllare in questa passata (non controllati dall'inizio della passata). */
    private function pending(MailList $list, DomainCheckService $svc, Carbon $pass, string $scope)
    {
        $domains = $svc->listDomains($list)->pluck('domain');
        $checks  = $svc->checksFor($domains->all());

        return $domains->filter(function ($domain) use ($checks, $pass, $scope) {
            /** @var DomainCheck|null $c */
            $c = $checks->get($domain);

            if ($c && $c->checked_at && $c->checked_at->gte($pass)) {
                return false;
            }

            return $scope === 'problems'
                ? ($c && in_array($c->status, DomainCheckService::PROBLEMS, true))
                : true;
        })->values();
    }

    /** Neutralizza le formule nei CSV (= + - @). */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) ? "'" . $value : $value;
    }
}
