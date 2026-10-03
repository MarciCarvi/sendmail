<x-app-layout>
    <x-slot name="title">Report</x-slot>

    <form method="GET" action="{{ route('reports.recipient') }}" class="row g-2 mb-4">
        <div class="col-md-5">
            <input type="email" name="email" class="form-control form-control-sm" placeholder="Cerca un destinatario per email…" required>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary btn-sm">Cerca</button></div>
    </form>

    @if($inProgress->isNotEmpty())
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white fw-semibold">Invii in corso</div>
            <ul class="list-group list-group-flush">
                @foreach($inProgress as $c)
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <div class="fw-medium">{{ $c->subject }}</div>
                            <div class="progress mt-1" style="height: 8px;">
                                <div class="progress-bar {{ $c->status === 'paused' ? 'bg-warning' : 'progress-bar-striped progress-bar-animated' }}"
                                     style="width: {{ $c->progress['percent'] }}%"></div>
                            </div>
                        </div>
                        <div class="small text-muted text-nowrap">
                            {{ number_format($c->progress['sent']) }} / {{ number_format($c->progress['total']) }}
                            @if($c->status === 'paused') · in pausa @endif
                        </div>
                        <a href="{{ route('reports.show', $c) }}" class="btn btn-sm btn-primary">Segui</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($campaigns->isEmpty() && $inProgress->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-2">Nessuna campagna inviata ancora.</p>
            <a href="{{ route('campaigns.index') }}" class="btn btn-primary btn-sm">Vai alle campagne</a>
        </div>
    @elseif($campaigns->isNotEmpty())
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Campagna</th>
                            <th>Inviata</th>
                            <th class="text-end">Inviati</th>
                            <th class="text-end">Open rate</th>
                            <th class="text-end">Click rate</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                        <tr>
                            <td class="fw-medium">{{ $campaign->subject }}</td>
                            <td class="text-muted small">{{ $campaign->sent_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">{{ number_format($campaign->stat_sent) }}</td>
                            <td class="text-end">
                                <span class="fw-semibold {{ $campaign->stat_open_rate >= 20 ? 'text-success' : ($campaign->stat_open_rate >= 10 ? 'text-warning' : 'text-muted') }}">
                                    {{ $campaign->stat_open_rate }}%
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="fw-semibold {{ $campaign->stat_click_rate >= 3 ? 'text-success' : 'text-muted' }}">
                                    {{ $campaign->stat_click_rate }}%
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('reports.show', $campaign) }}" class="btn btn-sm btn-outline-primary">
                                    Dettaglio
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
