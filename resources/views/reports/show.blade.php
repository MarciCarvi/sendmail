<x-app-layout>
    <x-slot name="title">Report — {{ $campaign->subject }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">← Campagne</a>
    </x-slot>

    {{-- KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-primary">{{ number_format($sent) }}</div>
                    <div class="small text-muted">Inviati</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-secondary">{{ $deliveryRate }}%</div>
                    <div class="small text-muted">Consegnati</div>
                    <div class="small text-muted">{{ $delivered }} / {{ $sent }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success">{{ $openRate }}%</div>
                    <div class="small text-muted">Open rate</div>
                    <div class="small text-muted">{{ $uniqueOpens }} unici / {{ $totalOpens }} tot</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-info">{{ $clickRate }}%</div>
                    <div class="small text-muted">Click rate</div>
                    <div class="small text-muted">{{ $uniqueClicks }} unici / {{ $totalClicks }} tot</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold {{ $unsubscribed > 0 ? 'text-warning' : 'text-muted' }}">{{ $unsubRate }}%</div>
                    <div class="small text-muted">Unsub rate</div>
                    <div class="small text-muted">{{ $unsubscribed }} disiscritti</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold {{ ($bounced + $failed) > 0 ? 'text-danger' : 'text-muted' }}">{{ $bounced + $failed }}</div>
                    <div class="small text-muted">Problemi</div>
                    <div class="small text-muted">
                        @if($bounced > 0) {{ $bounced }} bounce @endif
                        @if($failed > 0) {{ $failed }} falliti @endif
                        @if($bounced + $failed === 0) nessuno @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grafici aperture --}}
    @if(array_sum($hourData) > 0)
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header fw-semibold">Aperture per ora del giorno</div>
                <div class="card-body">
                    <canvas id="hourChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header fw-semibold">Aperture per giorno della settimana</div>
                <div class="card-body">
                    <canvas id="dowChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-3">

        {{-- Tabella aperture --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between">
                    <span>Chi ha aperto</span>
                    <span class="badge bg-success">{{ $uniqueOpens }}</span>
                </div>
                @if($openers->isEmpty())
                    <div class="card-body text-muted small">Nessuna apertura registrata.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Email</th>
                                    <th>Quando</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($openers as $open)
                                    <tr>
                                        <td class="small">
                                            {{ $open->subscriber?->email ?? '—' }}
                                            @if($open->subscriber?->full_name)
                                                <br><span class="text-muted">{{ $open->subscriber->full_name }}</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted text-nowrap">{{ $open->opened_at->format('d/m H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabella click --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between">
                    <span>Chi ha cliccato</span>
                    <span class="badge bg-info">{{ $uniqueClicks }}</span>
                </div>
                @if($clicks->isEmpty())
                    <div class="card-body text-muted small">Nessun click registrato.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Email</th>
                                    <th>Link</th>
                                    <th>Quando</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clicks as $click)
                                    <tr>
                                        <td class="small">{{ $click->subscriber?->email ?? '—' }}</td>
                                        <td class="small text-truncate" style="max-width:150px;">
                                            <a href="{{ $click->original_url }}" target="_blank" title="{{ $click->original_url }}">
                                                {{ parse_url($click->original_url, PHP_URL_HOST) ?? $click->original_url }}
                                            </a>
                                        </td>
                                        <td class="small text-muted text-nowrap">{{ $click->clicked_at->format('d/m H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>

    @if(array_sum($hourData) > 0)
    @push('scripts')
    <script>
    (function() {
        var hourLabels = @json($hourLabels);
        var hourData   = @json($hourData);
        var dowLabels  = @json($dowLabels);
        var dowData    = @json($dowData);

        var greenBase = 'rgba(25, 135, 84, 0.75)';
        var blueBase  = 'rgba(13, 110, 253, 0.75)';

        new Chart(document.getElementById('hourChart'), {
            type: 'bar',
            data: {
                labels: hourLabels,
                datasets: [{ label: 'Aperture', data: hourData,
                    backgroundColor: greenBase, borderRadius: 4 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false },
                    tooltip: { callbacks: { title: function(i) { return 'Ore ' + i[0].label; } } } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        new Chart(document.getElementById('dowChart'), {
            type: 'bar',
            data: {
                labels: dowLabels,
                datasets: [{ label: 'Aperture', data: dowData,
                    backgroundColor: blueBase, borderRadius: 4 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    })();
    </script>
    @endpush
    @endif

</x-app-layout>
