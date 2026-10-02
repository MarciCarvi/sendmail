<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\Unsubscribe;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request, MailList $list)
    {
        $query = $list->subscribers()->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $subscribers = $query->paginate(50)->withQueryString();

        $blacklistedEmails = Blacklist::whereIn('email',
            $subscribers->pluck('email')->map('strtolower')
        )->pluck('email')->flip()->toArray();

        return view('subscribers.index', compact('list', 'subscribers', 'blacklistedEmails'));
    }

    public function store(Request $request, MailList $list)
    {
        $request->validate([
            'email'      => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'company'    => 'nullable|string|max:150',
        ]);

        if (Blacklist::isBlacklisted($request->email)) {
            return back()->with('error', 'Email in blacklist. Non può essere aggiunta.');
        }

        if (Blacklist::isDomainBlocked($request->email)) {
            return back()->with('error', 'Dominio bloccato. Non può essere aggiunto.');
        }

        if (isset(Unsubscribe::suppressedFor($list->from_email)[strtolower($request->email)])) {
            return back()->with('error', 'Questa email si è disiscritta per questo cliente. Rimuovila dall\'elenco Disiscritti per poterla aggiungere.');
        }

        $existing = $list->subscribers()->where('email', $request->email)->first();

        if ($existing) {
            return back()->with('error', 'Email già presente in questa lista.');
        }

        $list->subscribers()->create($request->only('email', 'first_name', 'last_name', 'company'));

        return back()->with('success', 'Iscritto aggiunto.');
    }

    public function update(Request $request, MailList $list, Subscriber $subscriber)
    {
        $request->validate([
            'email'      => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'company'    => 'nullable|string|max:150',
            'status'     => 'required|in:subscribed,unsubscribed,bounced,complained',
        ]);

        $previousStatus = $subscriber->status;
        $data = $request->only('email', 'first_name', 'last_name', 'company', 'status');
        if ($data['status'] === 'unsubscribed' && $previousStatus !== 'unsubscribed') {
            $data['unsubscribed_at'] = now();
        }

        $subscriber->update($data);

        if ($subscriber->status === 'unsubscribed') {
            Unsubscribe::record($subscriber);
        } elseif ($previousStatus === 'unsubscribed' && $subscriber->status === 'subscribed') {
            Unsubscribe::clear($subscriber);
        }

        return back()->with('success', 'Iscritto aggiornato.');
    }

    public function destroy(MailList $list, Subscriber $subscriber)
    {
        $subscriber->delete();
        return back()->with('success', 'Iscritto eliminato.');
    }

    public function import(Request $request, MailList $list)
    {
        $request->validate([
            'csv'       => 'nullable|file|mimes:csv,txt|max:10240',
            'paste_text'=> 'nullable|string|max:2000000',
            'import_mode' => 'required|in:file,paste',
        ]);

        if ($request->import_mode === 'file') {
            $request->validate(['csv' => 'required|file|mimes:csv,txt|max:10240']);
            $content = file_get_contents($request->file('csv')->getRealPath());
        } else {
            $request->validate(['paste_text' => 'required|string']);
            $content = $request->paste_text;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        $lines = array_values($lines);

        if (empty($lines)) {
            return back()->with('error', 'Nessun dato trovato.');
        }

        // Auto-rileva separatore dalla prima riga
        $firstLine = $lines[0];
        if (substr_count($firstLine, "\t") >= substr_count($firstLine, ';')) {
            $separator = "\t";
        } else {
            $separator = ';';
        }

        $parseLine = fn(string $line) => array_map('trim', explode($separator, $line));

        $header = array_map('strtolower', $parseLine($lines[0]));
        $hasHeader = in_array('email', $header);

        $imported = 0;
        $unsubscribedForClient = Unsubscribe::suppressedFor($list->from_email);
        $skippedRows = [];   // [riga, email, motivo] per il log temporaneo
        $seen = [];

        $rows = $hasHeader ? array_slice($lines, 1) : $lines;
        $firstRowNumber = $hasHeader ? 2 : 1; // numero di riga (righe vuote escluse)

        foreach ($rows as $i => $line) {
            if (trim($line) === '') continue;

            $rowNumber = $firstRowNumber + $i;
            $cols = $parseLine($line);

            if ($hasHeader) {
                $data = array_combine($header, array_pad($cols, count($header), ''));
            } else {
                // senza intestazione: mapping posizionale email, nome, cognome, azienda
                $data = [
                    'email'      => $cols[0] ?? '',
                    'first_name' => $cols[1] ?? '',
                    'last_name'  => $cols[2] ?? '',
                    'company'    => $cols[3] ?? '',
                ];
            }

            $email = trim($data['email'] ?? '');

            $reason = null;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $reason = 'Email non valida';
            } elseif (Blacklist::isBlacklisted($email)) {
                $reason = 'In blacklist';
            } elseif (Blacklist::isDomainBlocked($email)) {
                $reason = 'Dominio bloccato';
            } elseif (isset($unsubscribedForClient[strtolower($email)])) {
                $reason = 'Disiscritto per questo cliente';
            } elseif (isset($seen[strtolower($email)])) {
                $reason = 'Duplicata nel file';
            } elseif ($list->subscribers()->where('email', $email)->exists()) {
                $reason = 'Già presente nella lista';
            }

            if ($reason !== null) {
                $skippedRows[] = [$rowNumber, $email, $reason];
                continue;
            }

            $seen[strtolower($email)] = true;

            $list->subscribers()->create([
                'email'      => $email,
                'first_name' => trim($data['first_name'] ?? $data['nome'] ?? ''),
                'last_name'  => trim($data['last_name'] ?? $data['cognome'] ?? ''),
                'company'    => trim($data['company'] ?? $data['azienda'] ?? ''),
            ]);

            $imported++;
        }

        $skipped = count($skippedRows);
        $response = back()->with('success', "Import completato: {$imported} aggiunti, {$skipped} saltati.");

        if ($skipped > 0) {
            $response->with('import_log', $this->storeImportLog($skippedRows));
        }

        return $response;
    }

    /**
     * Log temporaneo delle righe saltate: CSV in storage/app/import-logs (24 ore)
     * più un riepilogo e le prime righe da mostrare a schermo.
     */
    private function storeImportLog(array $skippedRows): array
    {
        $dir = storage_path('app/import-logs');
        @mkdir($dir, 0755, true);

        // pulizia dei log più vecchi di 24 ore
        foreach (glob($dir . '/*.csv') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }

        $token = Str::random(40);
        $handle = fopen("{$dir}/{$token}.csv", 'w');
        fputcsv($handle, ['riga', 'email', 'motivo']);
        foreach ($skippedRows as [$row, $email, $reason]) {
            fputcsv($handle, [$row, $this->csvSafe($email), $reason]);
        }
        fclose($handle);

        $counts = [];
        foreach ($skippedRows as [, , $reason]) {
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }
        arsort($counts);

        return [
            'token'  => $token,
            'counts' => $counts,
            'total'  => count($skippedRows),
            'rows'   => array_slice($skippedRows, 0, 200),
        ];
    }

    public function importLog(MailList $list, string $token)
    {
        $path = storage_path("app/import-logs/{$token}.csv");

        if (!preg_match('/^[A-Za-z0-9]{40}$/', $token) || !is_file($path) || filemtime($path) < time() - 86400) {
            return back()->with('error', 'Il log dell\'import è scaduto o non esiste.');
        }

        return response()->download($path, "import-saltati-lista-{$list->id}.csv", ['Content-Type' => 'text/csv']);
    }

    public function export(MailList $list): StreamedResponse
    {
        return response()->streamDownload(function () use ($list) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['email', 'first_name', 'last_name', 'company', 'status', 'subscribed_at']);

            $list->subscribers()->orderBy('email')->chunk(500, function ($subscribers) use ($handle) {
                foreach ($subscribers as $sub) {
                    fputcsv($handle, [
                        $this->csvSafe($sub->email),
                        $this->csvSafe($sub->first_name),
                        $this->csvSafe($sub->last_name),
                        $this->csvSafe($sub->company),
                        $sub->status,
                        $sub->subscribed_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, "lista-{$list->id}-iscritti.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralize CSV formula injection: a leading = + - @ (or tab/CR) makes
     * spreadsheet apps execute the cell as a formula. Prefix with an apostrophe.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    public function bulk(Request $request, MailList $list)
    {
        $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer|exists:sm_subscribers,id',
            'action'     => 'required|in:delete,status,domain,blacklist',
            'new_status' => 'required_if:action,status|nullable|in:subscribed,unsubscribed,bounced,complained',
            'old_domain' => 'required_if:action,domain|nullable|string',
            'new_domain' => 'required_if:action,domain|nullable|string',
        ]);

        $query = $list->subscribers()->whereIn('id', $request->ids);
        $count = $query->count();
        $statusSubscribers = $request->action === 'status' ? (clone $query)->get() : collect();

        match ($request->action) {
            'delete' => $query->delete(),
            'status' => $query->update(array_filter([
                'status'          => $request->new_status,
                'unsubscribed_at' => $request->new_status === 'unsubscribed' ? now() : null,
            ])),
            'domain' => $query->each(function (Subscriber $sub) use ($request) {
                $oldDomain = ltrim(trim($request->old_domain), '@');
                $newDomain = ltrim(trim($request->new_domain), '@');
                if (str_ends_with($sub->email, '@' . $oldDomain)) {
                    $sub->update(['email' => str_replace('@' . $oldDomain, '@' . $newDomain, $sub->email)]);
                }
            }),
            'blacklist' => $query->each(function (Subscriber $sub) use ($request, $list) {
                $lists = MailList::whereIn('id',
                    Subscriber::where('email', $sub->email)->pluck('list_id')
                )->get()->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->toArray();

                Blacklist::updateOrCreate(
                    ['email' => strtolower($sub->email)],
                    ['list_ids' => $lists, 'reason' => $request->bulk_reason, 'created_at' => now()]
                );
            }),
        };

        foreach ($statusSubscribers as $sub) {
            $sub->refresh();
            if ($request->new_status === 'unsubscribed') {
                Unsubscribe::record($sub);
            } elseif ($request->new_status === 'subscribed') {
                Unsubscribe::clear($sub);
            }
        }

        $label = match ($request->action) {
            'delete'     => "{$count} iscritti eliminati.",
            'status'     => "{$count} iscritti aggiornati a «{$request->new_status}».",
            'domain'     => "{$count} email aggiornate.",
            'blacklist'  => "{$count} iscritti aggiunti in blacklist.",
        };

        return back()->with('success', $label);
    }
}
