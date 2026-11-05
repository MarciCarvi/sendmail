<x-app-layout>
    <x-slot name="title">{{ $list->name }} — Controllo domini</x-slot>
    <x-slot name="actions">
        <div class="d-flex gap-2">
            <a href="{{ route('lists.subscribers.index', $list) }}" class="btn btn-outline-secondary btn-sm">← Iscritti</a>
            @if($summary['problems'] > 0)
                <a href="{{ route('lists.domains.export', $list) }}" class="btn btn-outline-secondary btn-sm">Esporta CSV</a>
            @endif
        </div>
    </x-slot>

    @php
        $tz = config('sendmail.report_timezone', 'Europe/Rome');
        $filtering = request('status') || request('search');
    @endphp

    <p class="text-muted small">
        Controlla se i <strong>domini</strong> degli indirizzi di questa lista esistono e ricevono posta (record DNS/MX).
        Elenca quelli <strong>problematici</strong> con il numero di utenti in lista, perché tu li verifichi uno per uno.
        Non esclude né elimina nulla. Il controllo si può lanciare quando vuoi; un dominio può risultare problematico
        anche per un guasto DNS momentaneo del suo provider, per questo c'è «Ricontrolla».
    </p>

    @if(session('success'))
        <div class="alert alert-success small">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger small">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    {{-- Controllo --}}
    <div class="card mb-3" x-data="domainChecker()">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <div>
                    <div class="fw-semibold">Controllo DNS</div>
                    <div class="small text-muted">
                        @if($summary['last_check'])
                            Ultimo controllo: {{ $summary['last_check']->copy()->setTimezone($tz)->format('d/m/Y H:i') }}.
                        @else
                            Nessun controllo eseguito su questa lista.
                        @endif
                        @if($summary['domains'] > 0)
                            {{ number_format($summary['domains']) }} domini diversi per {{ number_format($summary['users']) }} utenti.
                        @endif
                    </div>
                </div>
                <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                    @if($summary['problems'] > 0)
                        <button type="button" class="btn btn-outline-primary btn-sm" :disabled="running || {{ $summary['domains'] }} === 0"
                                @click="start('problems')">Ricontrolla solo i problematici</button>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" :disabled="running || {{ $summary['domains'] }} === 0"
                            @click="start('all')">
                        <span x-show="!running">{{ $summary['last_check'] ? 'Controlla di nuovo tutti i domini' : 'Controlla domini' }}</span>
                        <span x-show="running">Controllo in corso…</span>
                    </button>
                </div>
            </div>

            <div class="mt-3" x-show="running || (total > 0 && done)" style="display:none">
                <div class="progress" style="height: 8px">
                    <div class="progress-bar" :style="'width:' + (total ? Math.min(100, Math.round(checked / total * 100)) : 0) + '%'"></div>
                </div>
                <div class="small text-muted mt-1">
                    <span x-text="checked"></span> di <span x-text="total"></span> domini controllati
                    <span x-show="done" class="text-success ms-2">Completato.</span>
                </div>
            </div>
            <div class="small text-success mt-2" x-show="done && total === 0" style="display:none">Nessun dominio da controllare.</div>
            <div class="small text-danger mt-2" x-show="error" style="display:none">
                <span x-text="error"></span>
                <button type="button" class="btn btn-link btn-sm p-0 ms-1" x-show="!running && pass" @click="start(scope, true)">Riprendi</button>
            </div>
        </div>
    </div>

    {{-- Riepilogo --}}
    @if($summary['domains'] > 0 && $summary['last_check'])
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">Domini senza problemi</div>
                <div class="fs-5 fw-semibold text-success">{{ number_format($summary['ok']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">Domini problematici</div>
                <div class="fs-5 fw-semibold {{ $summary['problems'] ? 'text-danger' : '' }}">{{ number_format($summary['problems']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">Utenti in domini problematici</div>
                <div class="fs-5 fw-semibold">{{ number_format($summary['problem_users']) }}
                    <span class="small text-muted fw-normal">({{ number_format($summary['problem_active']) }} iscritti)</span></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">Mai controllati</div>
                <div class="fs-5 fw-semibold">{{ number_format($summary['unchecked']) }}</div>
            </div></div>
        </div>
    </div>
    @endif

    {{-- Filtri --}}
    @if($summary['problems'] > 0)
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cerca un dominio..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Tutti i problemi</option>
                @foreach(\App\Services\DomainCheckService::PROBLEMS as $st)
                    @if(($byStatus[$st] ?? 0) > 0)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ \App\Services\DomainCheckService::label($st)[1] }} ({{ $byStatus[$st] }})</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if($filtering)
                <a href="{{ route('lists.domains.index', $list) }}" class="btn btn-link btn-sm">Reset</a>
            @endif
        </div>
    </form>
    @endif

    {{-- Domini problematici --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Dominio</th>
                        <th>Problema</th>
                        <th class="text-end">Utenti in lista</th>
                        <th>Ultimo controllo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $row)
                        @php [$badge, $text] = \App\Services\DomainCheckService::label($row['check']->status); @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row['domain'] }}</div>
                                @if(!empty($suggestions[$row['domain']]))
                                    <div class="small text-warning-emphasis">Forse intendevi <strong>{{ $suggestions[$row['domain']] }}</strong></div>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $badge }}">{{ $text }}</span>
                                <div class="small text-muted mt-1">{{ $row['check']->detail }}</div>
                            </td>
                            <td class="text-end">
                                <div class="fw-semibold">{{ number_format($row['users']) }}</div>
                                <div class="small text-muted">{{ number_format($row['active']) }} iscritti</div>
                            </td>
                            <td class="small text-muted">{{ $row['check']->checked_at?->copy()->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('lists.subscribers.index', [$list, 'search' => '@' . $row['domain']]) }}"
                                   class="btn btn-sm btn-outline-secondary">Vedi utenti</a>
                                <form method="POST" action="{{ route('lists.domains.recheck', $list) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="domain" value="{{ $row['domain'] }}">
                                    <button class="btn btn-sm btn-outline-primary">Ricontrolla</button>
                                </form>
                                <a href="https://mxtoolbox.com/SuperTool.aspx?action=mx%3a{{ urlencode($row['domain']) }}&run=toolpage"
                                   target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-link"
                                   title="Verifica con uno strumento esterno (MXToolbox)">MXToolbox ↗</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                @if($summary['domains'] === 0)
                                    Questa lista non ha ancora iscritti.
                                @elseif(!$summary['last_check'])
                                    Nessun controllo eseguito: premi «Controlla domini».
                                @elseif($filtering)
                                    Nessun dominio con questi filtri.
                                @else
                                    Nessun dominio problematico. 🎉
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
    <div class="small text-muted mt-2">Orari nel fuso di {{ $tz }}. «Utenti in lista» conta tutti gli indirizzi della lista con quel dominio, qualunque stato; «iscritti» solo quelli attivi.</div>

    @push('scripts')
    <script>
    function domainChecker() {
        return {
            running: false, done: false, error: null, total: 0, checked: 0, scope: 'all', pass: null,
            async start(scope, resume = false) {
                this.running = true; this.done = false; this.error = null; this.scope = scope;
                if (!resume) { this.pass = null; this.total = 0; this.checked = 0; }
                try {
                    while (true) {
                        const r = await fetch('{{ route('lists.domains.check', $list) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ pass: this.pass, scope: scope }),
                        });
                        const d = await r.json().catch(() => ({}));
                        if (!r.ok) throw new Error(d.error || ('HTTP ' + r.status));
                        this.pass = d.pass;
                        this.checked += d.checked;
                        if (this.total === 0) this.total = d.checked + d.remaining;
                        if (d.remaining <= 0 || d.checked === 0) break;
                    }
                    this.done = true;
                    if (this.total > 0) setTimeout(() => location.reload(), 900);
                } catch (e) {
                    this.error = 'Controllo interrotto: ' + e.message + '.';
                }
                this.running = false;
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
