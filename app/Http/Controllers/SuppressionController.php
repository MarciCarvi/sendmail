<?php

namespace App\Http\Controllers;

use App\Models\AddressStatus;
use App\Models\MailList;
use App\Models\SenderProfile;
use App\Models\SesSuppression;
use App\Models\Setting;
use App\Models\Unsubscribe;
use App\Services\SuppressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Suppression list di Amazon SES divisa per cliente. La lista di SES è unica per account e regione: il cliente di un
 * indirizzo si ricava dalle liste in cui compare (dominio del mittente della lista, come per i Disiscritti).
 */
class SuppressionController extends Controller
{
    public function index(Request $request, SuppressionService $service)
    {
        $clients = $this->clients();

        $entries = $this->filtered($request)->paginate(50)->withQueryString();

        $emails   = $entries->pluck('email')->all();
        $statuses = AddressStatus::whereIn('email', $emails)->pluck('status', 'email')->all();
        $domains  = $this->domainsByEmail($emails);

        // Totali: tutti, per cliente, e indirizzi che non sono in nessuna lista
        $total      = SesSuppression::count();
        $perClient  = [];
        foreach (array_keys($clients) as $domain) {
            $perClient[$domain] = SesSuppression::whereExists($this->inLists($domain))->count();
        }
        $noClient   = SesSuppression::whereNotExists($this->inLists(null))->count();
        $complaints = SesSuppression::where('reason', SesSuppression::COMPLAINT)->count();

        $lastSync  = $service->lastSync();
        $lastError = $service->lastError();

        return view('suppression.index', compact(
            'entries', 'clients', 'statuses', 'domains', 'total', 'perClient', 'noClient', 'complaints', 'lastSync', 'lastError'
        ));
    }

    /** Una pagina della sincronizzazione completa; il browser la ripete finché SES ha altre voci. */
    public function sync(Request $request, SuppressionService $service)
    {
        $data = $request->validate([
            'token' => 'nullable|string|max:4096',
            'pass'  => 'nullable|string|max:40',
        ]);

        $pass = !empty($data['pass'])
            ? Carbon::parse($data['pass'])->setTimezone(config('app.timezone'))
            : now()->startOfSecond();

        try {
            $r       = $service->syncPage($data['token'] ?? null, $pass);
            $done    = empty($r['next']);
            $removed = $done ? $service->finishFull($pass) : 0;

            return response()->json([
                'pass'      => $pass->toIso8601String(),
                'next'      => $r['next'],
                'done'      => $done,
                'processed' => $r['processed'],
                'marked'    => $r['marked'],
                'removed'   => $removed,
                'total'     => SesSuppression::count(),
            ]);
        } catch (\Throwable $e) {
            $message = SuppressionService::friendlyError($e);
            Setting::set(SuppressionService::SETTING_ERROR, $message);

            return response()->json(['error' => $message], 422);
        }
    }

    /** CSV (`;` + BOM, orari di Roma) con gli stessi filtri della pagina: serve a chi aggiorna il database del cliente. */
    public function export(Request $request)
    {
        $query = $this->filtered($request);
        $tz    = config('sendmail.report_timezone', 'Europe/Rome');

        return response()->streamDownload(function () use ($query, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8: Excel mostra correttamente gli accenti
            fputcsv($out, ['Email', 'Motivo SES', 'In suppression list dal', 'Clienti', 'Stato in SendMail'], ';', '"', '\\');

            $query->reorder('id')->chunkById(500, function ($rows) use ($out, $tz) {
                $emails   = $rows->pluck('email')->all();
                $statuses = AddressStatus::whereIn('email', $emails)->pluck('status', 'email')->all();
                $domains  = $this->domainsByEmail($emails);

                foreach ($rows as $row) {
                    fputcsv($out, [
                        $this->csvSafe($row->email),
                        $row->reason === SesSuppression::COMPLAINT ? 'Reclamo spam' : 'Bounce',
                        $row->listed_at?->copy()->setTimezone($tz)->format('d/m/Y H:i'),
                        implode(', ', $domains[$row->email] ?? []),
                        match ($statuses[$row->email] ?? null) {
                            AddressStatus::BOUNCED    => 'Escluso (bounce)',
                            AddressStatus::COMPLAINED => 'Escluso (reclamo)',
                            default                   => 'Non segnato',
                        },
                    ], ';', '"', '\\');
                }
            });

            fclose($out);
        }, 'suppression-list-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Elenco filtrato (ricerca, motivo, cliente). Cliente: un dominio, oppure «_none» (in nessuna lista). */
    private function filtered(Request $request)
    {
        $query = SesSuppression::query()->orderByDesc('listed_at')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $request->search));
            $query->where('email', 'like', '%' . $search . '%');
        }

        if (in_array($request->reason, [SesSuppression::BOUNCE, SesSuppression::COMPLAINT], true)) {
            $query->where('reason', $request->reason);
        }

        if ($request->client === '_none') {
            $query->whereNotExists($this->inLists(null));
        } elseif ($request->filled('client')) {
            $query->whereExists($this->inLists(strtolower((string) $request->client)));
        }

        return $query;
    }

    /** Subquery: l'indirizzo è iscritto a una lista (non di test), eventualmente di un cliente (dominio del mittente). */
    private function inLists(?string $domain): \Closure
    {
        return function ($q) use ($domain) {
            $q->select(DB::raw(1))
                ->from('sm_subscribers as s')
                ->join('sm_lists as l', 'l.id', '=', 's.list_id')
                ->whereColumn('s.email', 'sm_ses_suppressions.email')
                ->where('l.is_test', false);

            if ($domain) {
                $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $domain);
                $q->where('l.from_email', 'like', '%@' . $like);
            }
        };
    }

    /** Clienti = domini del mittente delle liste (non di test), con il nome dei profili di invio su quel dominio. */
    private function clients(): array
    {
        $domains = MailList::where('is_test', false)->pluck('from_email')
            ->map(fn($e) => Unsubscribe::domainOf($e))
            ->filter()->unique()->sort()->values();

        $names = SenderProfile::orderBy('name')->get()
            ->groupBy(fn($p) => Unsubscribe::domainOf($p->from_email))
            ->map(fn($g) => $g->pluck('name')->join(', '));

        return $domains->mapWithKeys(fn($d) => [$d => $names[$d] ?? ''])->all();
    }

    /** Per ogni indirizzo, i domini cliente delle liste (non di test) in cui compare: [email => [domini]]. */
    private function domainsByEmail(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        $map = [];
        $rows = DB::table('sm_subscribers as s')
            ->join('sm_lists as l', 'l.id', '=', 's.list_id')
            ->whereIn('s.email', $emails)
            ->where('l.is_test', false)
            ->get(['s.email', 'l.from_email']);

        foreach ($rows as $row) {
            $domain = Unsubscribe::domainOf($row->from_email);
            if ($domain !== '') {
                $map[strtolower($row->email)][$domain] = $domain;
            }
        }

        return array_map(fn($d) => array_values($d), $map);
    }

    /** Neutralizza le formule nei CSV (= + - @). */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) ? "'" . $value : $value;
    }
}
