<x-app-layout>
    <x-slot name="title">Suppression list</x-slot>

    @php
        $tz = config('sendmail.report_timezone', 'Europe/Rome');
        $filtering = request('search') || request('reason') || request('client');
    @endphp

    <p class="text-muted small">
        Indirizzi che <strong>Amazon SES si rifiuta di raggiungere</strong> perché hanno già generato un bounce permanente
        o un reclamo (in un qualsiasi invio dell'account, anche di altri clienti). La lista è <strong>unica per
        account e regione</strong>: qui ne vedi una copia, divisa per <strong>cliente</strong> (dominio del mittente
        delle liste in cui l'indirizzo compare). Gli indirizzi sincronizzati vengono segnati e <strong>esclusi dagli
        invii</strong> prima che SES li rifiuti, così non risultano come non consegnati nel rapporto del cliente.
        Per togliere un indirizzo dalla lista serve la console AWS (SES → Suppression list), dopo averne capito il motivo.
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($lastError)
        <div class="alert alert-warning small">
            <strong>Ultima sincronizzazione non riuscita.</strong> {{ $lastError }}
        </div>
    @endif

    {{-- Sincronizzazione con SES --}}
    <div class="card mb-3" x-data="suppressionSync()">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div>
                <div class="fw-semibold">Sincronizzazione con SES</div>
                <div class="small text-muted">
                    @if($lastSync)
                        Ultima: {{ $lastSync->copy()->setTimezone($tz)->format('d/m/Y H:i') }}.
                        Da qui in poi si aggiorna da sola prima di ogni invio e ogni giorno.
                    @else
                        Mai eseguita. La prima sincronizzazione si fa a mano; poi si aggiorna da sola prima di ogni invio e ogni giorno.
                    @endif
                </div>
            </div>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="small text-muted" x-show="running">Elaborati: <strong x-text="processed"></strong></span>
                <span class="small text-success" x-show="done && !running" x-text="summary"></span>
                <button type="button" class="btn btn-primary btn-sm" :disabled="running" @click="start()">
                    <span x-show="!running">{{ $lastSync ? 'Sincronizza ora' : 'Prima sincronizzazione' }}</span>
                    <span x-show="running">Sincronizzazione in corso…</span>
                </button>
            </div>
        </div>
        <div class="px-3 pb-3 small text-danger" x-show="error" x-text="error"></div>
    </div>

    @if(!$lastSync && $total === 0)
        <div class="alert alert-info small">
            <strong>Prima di sincronizzare:</strong> l'utente IAM usato da SendMail (Impostazioni) deve poter leggere la lista,
            quindi la sua policy deve includere l'azione <code>ses:ListSuppressedDestinations</code>. La lista letta è quella
            della regione SES configurata in Impostazioni.
        </div>
    @endif

    {{-- Riepilogo per cliente --}}
    @if($total > 0 || $filtering)
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <a href="{{ route('suppression.index', array_filter(['reason' => request('reason'), 'search' => request('search')])) }}"
           class="badge text-decoration-none {{ request('client') ? 'text-bg-light border' : 'text-bg-primary' }}">Tutti · {{ $total }}</a>
        @foreach($clients as $domain => $name)
            <a href="{{ route('suppression.index', array_filter(['client' => $domain, 'reason' => request('reason'), 'search' => request('search')])) }}"
               class="badge text-decoration-none {{ request('client') === $domain ? 'text-bg-primary' : 'text-bg-light border' }}">
                {{ $domain }}@if($name) — {{ $name }}@endif · {{ $perClient[$domain] ?? 0 }}
            </a>
        @endforeach
        <a href="{{ route('suppression.index', array_filter(['client' => '_none', 'reason' => request('reason'), 'search' => request('search')])) }}"
           class="badge text-decoration-none {{ request('client') === '_none' ? 'text-bg-primary' : 'text-bg-light border' }}"
           title="Indirizzi che non compaiono in nessuna lista di questa installazione">Non nelle mie liste · {{ $noClient }}</a>
        @if($complaints > 0)
            <span class="small text-danger ms-2">{{ $complaints }} reclami spam in totale</span>
        @endif
    </div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cerca per email..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <select name="client" class="form-select form-select-sm">
                <option value="">Tutti i clienti</option>
                @foreach($clients as $domain => $name)
                    <option value="{{ $domain }}" @selected(request('client') === $domain)>{{ $domain }}@if($name) — {{ $name }}@endif</option>
                @endforeach
                <option value="_none" @selected(request('client') === '_none')>Non nelle mie liste</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="reason" class="form-select form-select-sm">
                <option value="">Tutti i motivi</option>
                <option value="BOUNCE" @selected(request('reason') === 'BOUNCE')>Bounce</option>
                <option value="COMPLAINT" @selected(request('reason') === 'COMPLAINT')>Reclamo spam</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if($filtering)
                <a href="{{ route('suppression.index') }}" class="btn btn-link btn-sm">Reset</a>
            @endif
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('suppression.export', request()->only('search', 'client', 'reason')) }}"
               class="btn btn-outline-secondary btn-sm">Esporta CSV{{ $filtering ? ' (filtrato)' : '' }}</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Motivo SES</th>
                        <th>Clienti</th>
                        <th>In SES dal</th>
                        <th>Stato in SendMail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        @php
                            $status = $statuses[$entry->email] ?? null;
                            $entryDomains = $domains[$entry->email] ?? [];
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $entry->email }}</td>
                            <td>
                                @if($entry->reason === 'COMPLAINT')
                                    <span class="badge text-bg-danger">Reclamo spam</span>
                                @else
                                    <span class="badge text-bg-secondary">Bounce</span>
                                @endif
                            </td>
                            <td class="small">
                                @forelse($entryDomains as $d)
                                    <div>{{ $d }}@if(!empty($clients[$d])) <span class="text-muted">— {{ $clients[$d] }}</span>@endif</div>
                                @empty
                                    <span class="text-muted">— (in nessuna lista)</span>
                                @endforelse
                            </td>
                            <td class="small text-muted">{{ $entry->listed_at?->copy()->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                            <td class="small">
                                @if($status === 'complained')
                                    <span class="badge text-bg-danger">Escluso (reclamo)</span>
                                @elseif($status === 'bounced')
                                    <span class="badge text-bg-warning">Escluso (bounce)</span>
                                @else
                                    <span class="badge text-bg-light border" title="L'indirizzo è stato riabilitato in SendMail o non è mai stato segnato: SES lo blocca comunque">Non segnato</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                {{ $filtering ? 'Nessun indirizzo con questi filtri.' : 'Nessun indirizzo: esegui la sincronizzazione con SES.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
    <div class="small text-muted mt-2">Orari nel fuso di {{ $tz }}.</div>

    @push('scripts')
    <script>
    function suppressionSync() {
        return {
            running: false, done: false, error: null, processed: 0, summary: '',
            async start() {
                this.running = true; this.done = false; this.error = null; this.processed = 0;
                let token = null, pass = null, marked = 0, removed = 0, total = 0;
                try {
                    while (true) {
                        const r = await fetch('{{ route('suppression.sync') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ token: token, pass: pass }),
                        });
                        const d = await r.json().catch(() => ({}));
                        if (!r.ok) throw new Error(d.error || ('HTTP ' + r.status));
                        pass = d.pass; token = d.next;
                        this.processed += d.processed; marked += d.marked; removed += d.removed; total = d.total;
                        if (d.done) break;
                    }
                    this.done = true;
                    this.summary = 'Completata: ' + total + ' indirizzi, ' + marked + ' nuovi segnati come esclusi'
                        + (removed ? ', ' + removed + ' tolti perché non più in SES' : '') + '.';
                    setTimeout(() => location.reload(), 1500);
                } catch (e) {
                    this.error = 'Sincronizzazione interrotta: ' + e.message;
                }
                this.running = false;
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
