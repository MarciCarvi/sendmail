<x-app-layout>
    <x-slot name="title">Report — {{ $campaign->subject }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">← Campagne</a>
    </x-slot>

    @if($progress)
        @php
            $driverCfg = array_merge($progress, [
                'batchUrl'    => route('campaigns.process-batch', $campaign),
                'pauseUrl'    => route('campaigns.pause', $campaign),
                'resumeUrl'   => route('campaigns.resume', $campaign),
                'csrf'        => csrf_token(),
                'kpiSelector' => '#kpi-row',
            ]);
        @endphp
        <div class="card border-primary mb-4" x-data="sendingDriver({{ Js::from($driverCfg) }})" x-init="init()">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <strong x-text="status === 'sent' ? 'Invio completato' : (status === 'paused' ? 'Invio in pausa' : 'Invio in corso…')"></strong>
                    <span class="text-muted small" x-show="status === 'sending'">
                        prosegue finché questa pagina o la pagina della campagna resta aperta
                    </span>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-warning btn-sm" x-show="status === 'sending'" @click="pause()">⏸ Metti in pausa</button>
                        <button type="button" class="btn btn-success btn-sm" x-show="status === 'paused'" @click="resume()">▶ Riprendi invio</button>
                    </div>
                </div>
                <div class="progress mb-2" style="height: 20px;">
                    <div class="progress-bar"
                         :class="status === 'sent' ? 'bg-success' : (status === 'paused' ? 'bg-warning' : 'bg-primary progress-bar-striped progress-bar-animated')"
                         :style="'width:' + percent + '%'" x-text="percent + '%'"></div>
                </div>
                <div class="d-flex flex-wrap gap-3 small text-muted">
                    <span>✅ <strong x-text="sent"></strong> inviati</span>
                    <span>⏳ <strong x-text="pending"></strong> in coda</span>
                    <span x-show="failed > 0" class="text-danger">❌ <strong x-text="failed"></strong> falliti</span>
                    <span>📬 <strong x-text="total"></strong> totali</span>
                </div>
                <div x-show="error" x-cloak class="alert alert-danger p-2 small mt-3 mb-0">
                    <span x-text="error"></span>
                    <button type="button" class="btn btn-sm btn-danger ms-2" x-show="!running" @click="retry()">Riprendi</button>
                </div>
            </div>
        </div>
        @push('scripts')
            @include('campaigns._sending-driver')
        @endpush
    @endif

    {{-- KPI cards --}}
    <div class="row g-3 mb-4" id="kpi-row">
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
                    <div class="fs-2 fw-bold {{ ($bounced + $failed + $complaints) > 0 ? 'text-danger' : 'text-muted' }}">{{ $bounced + $failed + $complaints }}</div>
                    <div class="small text-muted">Problemi</div>
                    <div class="small text-muted">
                        @if($bounced > 0) {{ $bounced }} bounce ({{ $bouncedPermanent }} permanenti, {{ $bounced - $bouncedPermanent }} temporanei) @endif
                        @if($failed > 0) {{ $failed }} falliti @endif
                        @if($complaints > 0) {{ $complaints }} complaint @endif
                        @if($bounced + $failed + $complaints === 0) nessuno @endif
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
