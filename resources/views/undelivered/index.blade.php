<x-app-layout>
    <x-slot name="title">Non consegnati</x-slot>

    <p class="text-muted small">
        Indirizzi che non hanno ricevuto <strong>{{ $threshold }}</strong> invii consecutivi (nessuna consegna, o bounce).
        Sono esclusi dagli invii finché non li riabiliti. Vale per tutti i clienti, come la
        <a href="{{ route('blacklist.index') }}">Blacklist</a>.
        @if($reliableSince)
            Si considerano gli invii dal {{ $reliableSince->format('d/m/Y H:i') }} (inizio del registro eventi).
        @else
            Nessun evento SES registrato ancora: l'analisi parte dopo la prima campagna con la versione 1.5.1 o successiva.
        @endif
    </p>

    {{-- Regola e analisi --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <form method="POST" action="{{ route('undelivered.settings') }}" class="d-flex flex-wrap gap-3 align-items-end">
                @csrf @method('PUT')
                <div>
                    <label class="form-label small fw-semibold mb-1">Invii consecutivi</label>
                    <input type="number" name="threshold" min="1" max="20" value="{{ old('threshold', $threshold) }}"
                           class="form-control form-control-sm" style="width: 90px">
                </div>
                <div>
                    <label class="form-label small fw-semibold mb-1">Attesa prima di giudicare (ore)</label>
                    <input type="number" name="grace_hours" min="0" max="720" value="{{ old('grace_hours', $grace) }}"
                           class="form-control form-control-sm" style="width: 120px">
                </div>
                <button class="btn btn-outline-secondary btn-sm">Salva regola</button>
            </form>
            <form method="POST" action="{{ route('undelivered.evaluate') }}" class="ms-auto">
                @csrf
                <button class="btn btn-primary btn-sm">Analizza ora</button>
            </form>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    {{-- Domini più frequenti + controllo DNS --}}
    @if($total > 0)
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Domini più frequenti</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($topDomains as $d)
                            <a href="{{ route('undelivered.index', ['domain' => $d->domain]) }}"
                               class="badge text-bg-light border text-decoration-none">{{ $d->domain }} · {{ $d->n }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100" x-data="mxChecker({{ $toCheck }})">
                <div class="card-body">
                    <div class="fw-semibold mb-1">Controllo DNS dei domini</div>
                    <div class="small text-muted mb-2">Verifica se il dominio ha record MX (può ricevere posta).</div>
                    <button type="button" class="btn btn-outline-primary btn-sm" :disabled="running || remaining === 0" @click="start()">
                        <span x-show="!running">Controlla domini</span>
                        <span x-show="running">Controllo in corso…</span>
                    </button>
                    <span class="small text-muted ms-2" x-show="remaining > 0 || running">da controllare: <strong x-text="remaining"></strong></span>
                    <span class="small text-success ms-2" x-show="done && !running">Controllo completato.</span>
                    <div class="small text-danger mt-1" x-show="error" x-text="error"></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cerca per email..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <input type="text" name="domain" class="form-control form-control-sm" placeholder="Dominio" value="{{ request('domain') }}">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if(request('search') || request('domain'))
                <a href="{{ route('undelivered.index') }}" class="btn btn-link btn-sm">Reset</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Dominio</th>
                        <th>Ultimi invii</th>
                        <th>Segnalato il</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        @php
                            $mx = [
                                'ok'      => ['success',   'MX ok'],
                                'no_mx'   => ['warning',   'Nessun MX (risolve)'],
                                'null_mx' => ['danger',    'Non riceve posta'],
                                'dead'    => ['danger',    'Dominio non risolvibile'],
                                'unknown' => ['secondary', 'Controllo non riuscito'],
                            ][$entry->mx_status] ?? null;
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $entry->email }}</td>
                            <td>
                                <div>{{ $entry->domain }}</div>
                                @if($mx)<span class="badge text-bg-{{ $mx[0] }}">{{ $mx[1] }}</span>@endif
                                @if($entry->suggestion)
                                    <div class="small text-warning-emphasis">Forse intendevi <strong>{{ $entry->suggestion }}</strong></div>
                                @endif
                            </td>
                            <td class="small">
                                @foreach(($entry->evidence ?? []) as $ev)
                                    <div class="text-muted">{{ \Illuminate\Support\Carbon::parse($ev['sent_at'])->format('d/m/Y H:i') }} — {{ $ev['reason'] }}
                                        <a href="{{ route('reports.undelivered', $ev['campaign_id']) }}" class="text-decoration-none">(campagna)</a>
                                    </div>
                                @endforeach
                            </td>
                            <td class="small text-muted">{{ $entry->flagged_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('undelivered.reinstate', $entry) }}" class="d-inline"
                                      onsubmit="return confirm('Riabilitare «{{ $entry->email }}»? Tornerà a ricevere le campagne; verrà segnalato di nuovo solo se fallisce altri {{ $threshold }} invii.')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Riabilita</button>
                                </form>
                                <form method="POST" action="{{ route('undelivered.blacklist', $entry) }}" class="d-inline"
                                      onsubmit="return confirm('Aggiungere «{{ $entry->email }}» alla blacklist (blocco per tutti)?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">Blacklist</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nessun indirizzo segnalato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>

    @push('scripts')
    <script>
    function mxChecker(initial) {
        return {
            remaining: initial, running: false, done: false, error: null,
            async start() {
                this.running = true; this.done = false; this.error = null;
                try {
                    while (this.remaining > 0) {
                        const r = await fetch('{{ route('undelivered.check-mx') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                        });
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        const d = await r.json();
                        this.remaining = d.remaining;
                        if (d.checked === 0) break;
                    }
                    this.done = true;
                    setTimeout(() => location.reload(), 600);
                } catch (e) {
                    this.error = 'Controllo interrotto (' + e.message + '). Riprova.';
                }
                this.running = false;
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
