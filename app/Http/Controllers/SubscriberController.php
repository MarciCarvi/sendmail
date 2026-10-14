<?php

namespace App\Http\Controllers;

use App\Models\AddressStatus;
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

        $emails = $subscribers->pluck('email')->map('strtolower');

        // [email => voce]: la voce porta motivo e data da mostrare accanto al badge
        $blacklistedEmails = Blacklist::whereIn('email', $emails)->get()
            ->keyBy(fn($b) => strtolower($b->email));
        $addressStates = AddressStatus::whereIn('email', $emails)->get()->keyBy('email');

        return view('subscribers.index', compact('list', 'subscribers', 'blacklistedEmails', 'addressStates'));
    }

    public function store(Request $request, MailList $list)
    {
        $request->validate([
            'email'      => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'company'    => 'nullable|string|max:150',
        ]);

        if (Blacklist::isDomainBlocked($request->email)) {
            return back()->with('error', 'Dominio bloccato. Non può essere aggiunto.');
        }

        $existing = $list->subscribers()->where('email', $request->email)->first();

        if ($existing) {
            return back()->with('error', 'Email già presente in questa lista.');
        }

        // L'indirizzo entra sempre nella lista, con lo stato che ha già (bounced, complained, disiscritto per il
        // cliente); se è in blacklist il badge si vede nell'elenco e non riceverà campagne.
        [$status, $unsubscribedAt] = $this->initialStatus(
            $request->email,
            AddressStatus::map(),
            Unsubscribe::datesFor($list->from_email)
        );

        $list->subscribers()->create($request->only('email', 'first_name', 'last_name', 'company') + [
            'status'          => $status,
            'unsubscribed_at' => $unsubscribedAt,
        ]);

        $notes = match ($status) {
            'bounced'      => 'con stato «bounced»: l\'indirizzo risulta rimbalzato',
            'complained'   => 'con stato «complained»: l\'indirizzo ha segnalato spam',
            'unsubscribed' => 'con stato «unsubscribed»: si era disiscritto per questo cliente',
            default        => null,
        };

        $message = 'Iscritto aggiunto' . ($notes ? " {$notes} e non riceverà le campagne (dettagli in Report → Cerca destinatario)" : '') . '.';

        if (Blacklist::isBlacklisted($request->email)) {
            $message .= ' L\'indirizzo è in blacklist: non riceverà le campagne.';
        }

        return back()->with('success', $message);
    }

    /**
     * Stato con cui nasce una riga: bounce/reclamo dell'indirizzo (valgono per ogni lista), altrimenti disiscritto per
     * il cliente della lista, altrimenti iscritto. La blacklist non è uno stato: si vede dal badge.
     *
     * @return array{0: string, 1: ?\DateTimeInterface}
     */
    private function initialStatus(string $email, array $addressStates, array $unsubscribedDates): array
    {
        $key = strtolower($email);

        if (isset($addressStates[$key])) {
            return [$addressStates[$key], null];
        }

        if (isset($unsubscribedDates[$key])) {
            return ['unsubscribed', $unsubscribedDates[$key]];
        }

        return ['subscribed', null];
    }

    /**
     * Cambio di stato fatto a mano: vale per l'indirizzo, quindi per tutte le liste (vale l'ultimo stato impostato).
     * Rimettere «subscribed» riabilita l'indirizzo ovunque; bounced/complained lo segnano ovunque.
     */
    private function syncAddressStatus(Subscriber $subscriber, string $previousStatus): void
    {
        if ($subscriber->status === $previousStatus) {
            return;
        }

        match ($subscriber->status) {
            'subscribed'            => AddressStatus::reinstate($subscriber->email),
            'bounced', 'complained' => AddressStatus::mark($subscriber->email, $subscriber->status, 'Impostato a mano', now(), force: true),
            default                 => null,
        };
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

        $this->syncAddressStatus($subscriber, $previousStatus);

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
        $unsubscribedDates = Unsubscribe::datesFor($list->from_email);
        $addressStates = AddressStatus::map();
        $skippedRows = [];   // [riga, email, motivo] per il log temporaneo
        $noteRows = [];      // [riga, email, esito]: importate ma con uno stato particolare
        $withState = ['bounced' => 0, 'complained' => 0, 'unsubscribed' => 0, 'blacklist' => 0];
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
            } elseif (Blacklist::isDomainBlocked($email)) {
                $reason = 'Dominio bloccato';
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

            // Ogni indirizzo entra con lo stato che ha già; la blacklist si vede dal badge e blocca l'invio
            [$status, $unsubscribedAt] = $this->initialStatus($email, $addressStates, $unsubscribedDates);

            $list->subscribers()->create([
                'email'           => $email,
                'first_name'      => trim($data['first_name'] ?? $data['nome'] ?? ''),
                'last_name'       => trim($data['last_name'] ?? $data['cognome'] ?? ''),
                'company'         => trim($data['company'] ?? $data['azienda'] ?? ''),
                'status'          => $status,
                'unsubscribed_at' => $unsubscribedAt,
            ]);

            $imported++;

            if ($status !== 'subscribed') {
                $withState[$status]++;
                $noteRows[] = [$rowNumber, $email, match ($status) {
                    'bounced'    => 'Importata come bounced (rimbalzata in precedenza)',
                    'complained' => 'Importata come complained (segnalata come spam in precedenza)',
                    default      => 'Importata come unsubscribed (disiscritta per questo cliente)',
                }];
            }

            if (Blacklist::isBlacklisted($email)) {
                $withState['blacklist']++;
                $noteRows[] = [$rowNumber, $email, 'Importata ma in blacklist (non riceverà campagne)'];
            }
        }

        $skipped = count($skippedRows);
        $message = "Import completato: {$imported} aggiunti, {$skipped} saltati.";

        $labels = ['bounced' => 'bounced', 'complained' => 'complained', 'unsubscribed' => 'unsubscribed', 'blacklist' => 'in blacklist'];
        $stateParts = [];
        foreach ($withState as $key => $n) {
            if ($n > 0) {
                $stateParts[] = "{$n} {$labels[$key]}";
            }
        }
        if ($stateParts) {
            $message .= ' Tra gli aggiunti, con uno stato che esclude l\'invio: ' . implode(', ', $stateParts) . '.';
        }

        $response = back()->with('success', $message);

        $logRows = array_merge($skippedRows, $noteRows);
        if ($logRows) {
            usort($logRows, fn($a, $b) => $a[0] <=> $b[0]);
            $response->with('import_log', $this->storeImportLog($logRows));
        }

        return $response;
    }

    /**
     * Log temporaneo dell'import (righe saltate e righe importate con uno stato particolare): CSV in
     * storage/app/import-logs (24 ore) più un riepilogo e le prime righe da mostrare a schermo.
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
        fputcsv($handle, ['riga', 'email', 'esito']);
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

        return response()->download($path, "import-log-lista-{$list->id}.csv", ['Content-Type' => 'text/csv']);
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
            $previousStatus = $sub->status;
            $sub->refresh();
            if ($request->new_status === 'unsubscribed') {
                Unsubscribe::record($sub);
            } elseif ($request->new_status === 'subscribed') {
                Unsubscribe::clear($sub);
            }

            $this->syncAddressStatus($sub, $previousStatus);
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
