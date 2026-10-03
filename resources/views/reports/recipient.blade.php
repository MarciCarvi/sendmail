<x-app-layout>
    <x-slot name="title">Cerca destinatario</x-slot>
    <x-slot name="actions">
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← Report</a>
    </x-slot>

    <form method="GET" action="{{ route('reports.recipient') }}" class="row g-2 mb-4">
        <div class="col-md-6">
            <input type="email" name="email" class="form-control" placeholder="Indirizzo email da cercare…"
                   value="{{ $email }}" required autofocus>
        </div>
        <div class="col-auto"><button class="btn btn-primary">Cerca</button></div>
    </form>

    @if($timeline)
        @php $t = $timeline; @endphp

        {{-- Stato generale dell'indirizzo --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ $t['email'] }}</div>
            <div class="card-body">
                @if($t['subscriptions']->isEmpty() && empty($t['items']))
                    <p class="text-muted mb-0">Nessuna iscrizione né invio trovati per questo indirizzo.</p>
                @else
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if($t['blacklisted'])<span class="badge text-bg-danger">In blacklist</span>@endif
                        @if($t['undelivered'])<span class="badge text-bg-warning">Segnalato come non consegnato</span>@endif
                        @foreach($t['unsubscribes'] as $u)
                            <span class="badge text-bg-secondary">Disiscritto per {{ $u->sender_domain }}</span>
                        @endforeach
                    </div>
                    @if($t['subscriptions']->isNotEmpty())
                        <div class="small fw-semibold mb-1">Iscrizioni</div>
                        <ul class="small mb-0">
                            @foreach($t['subscriptions'] as $sub)
                                <li>
                                    {{ $sub->list?->name ?? '—' }} · stato <strong>{{ $sub->status }}</strong>
                                    @if($sub->unsubscribed_at) (disiscritto il {{ $sub->unsubscribed_at->format('d/m/Y H:i') }}) @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </div>

        {{-- Cosa è successo per ogni invio --}}
        @foreach($t['items'] as $item)
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="fw-semibold">{{ $item['campaign']?->subject ?? 'Campagna eliminata' }}</span>
                    @if($item['campaign'])
                        <a href="{{ route('reports.show', $item['campaign']) }}" class="small ms-auto">Report della campagna</a>
                    @endif
                </div>
                <ul class="list-group list-group-flush">
                    @foreach($item['steps'] as $st)
                        @php $color = ['ok' => 'success', 'bad' => 'danger', 'warn' => 'warning', 'info' => 'primary', 'neutral' => 'secondary'][$st['kind']] ?? 'secondary'; @endphp
                        <li class="list-group-item d-flex gap-3 small">
                            <span class="text-muted text-nowrap" style="width: 130px">{{ $st['local']->format('d/m/Y H:i:s') }}</span>
                            <span class="text-{{ $color }}">{{ $st['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        @if(!empty($t['items']))
            <div class="small text-muted">Orari nel fuso di {{ config('sendmail.report_timezone', 'Europe/Rome') }}.</div>
        @endif
    @endif
</x-app-layout>
