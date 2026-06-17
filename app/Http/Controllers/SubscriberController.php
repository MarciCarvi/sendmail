<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Subscriber;
use Illuminate\Http\Request;
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

        $subscriber->update($request->only('email', 'first_name', 'last_name', 'company', 'status'));

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
        $skipped  = 0;

        $rows = $hasHeader ? array_slice($lines, 1) : $lines;

        foreach ($rows as $line) {
            if (trim($line) === '') continue;

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
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            if (Blacklist::isBlacklisted($email) || Blacklist::isDomainBlocked($email)) {
                $skipped++;
                continue;
            }

            if ($list->subscribers()->where('email', $email)->exists()) {
                $skipped++;
                continue;
            }

            $list->subscribers()->create([
                'email'      => $email,
                'first_name' => trim($data['first_name'] ?? $data['nome'] ?? ''),
                'last_name'  => trim($data['last_name'] ?? $data['cognome'] ?? ''),
                'company'    => trim($data['company'] ?? $data['azienda'] ?? ''),
            ]);

            $imported++;
        }

        return back()->with('success', "Import completato: {$imported} aggiunti, {$skipped} saltati.");
    }

    public function export(MailList $list): StreamedResponse
    {
        return response()->streamDownload(function () use ($list) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['email', 'first_name', 'last_name', 'company', 'status', 'subscribed_at']);

            $list->subscribers()->orderBy('email')->chunk(500, function ($subscribers) use ($handle) {
                foreach ($subscribers as $sub) {
                    fputcsv($handle, [
                        $sub->email,
                        $sub->first_name,
                        $sub->last_name,
                        $sub->company,
                        $sub->status,
                        $sub->subscribed_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, "lista-{$list->id}-iscritti.csv", ['Content-Type' => 'text/csv']);
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

        match ($request->action) {
            'delete' => $query->delete(),
            'status' => $query->update(['status' => $request->new_status]),
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

        $label = match ($request->action) {
            'delete'     => "{$count} iscritti eliminati.",
            'status'     => "{$count} iscritti aggiornati a «{$request->new_status}».",
            'domain'     => "{$count} email aggiornate.",
            'blacklist'  => "{$count} iscritti aggiunti in blacklist.",
        };

        return back()->with('success', $label);
    }
}
