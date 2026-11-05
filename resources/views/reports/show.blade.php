<x-app-layout>
    <x-slot name="title">Report — {{ $campaign->subject }}</x-slot>
    <x-slot name="actions">
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Documenti</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('reports.summary', $campaign) }}" target="_blank">Rapporto per il cliente (stampa / PDF)</a></li>
                    <li><a class="dropdown-item" href="{{ route('reports.recipients.export', $campaign) }}">CSV destinatari (uso tecnico)</a></li>
                    <li><a class="dropdown-item" href="{{ route('reports.undelivered.export', $campaign) }}">CSV non consegnati</a></li>
                </ul>
            </div>
            <a href="{{ route('reports.undelivered', $campaign) }}" class="btn btn-outline-secondary btn-sm">
                Non consegnati ({{ number_format($undeliveredCount) }})
            </a>
            <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">← Campagne</a>
        </div>
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
                    <div class="fs-2 fw-bold text-success">{{ number_format($totalOpens) }}</div>
                    <div class="small text-muted">Aperture</div>
                    <div class="small text-muted">{{ $openRate }} ogni 100 inviati · indicativo</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-info">{{ number_format($totalClicks) }}</div>
                    <div class="small text-muted">Click</div>
                    <div class="small text-muted">{{ $clickRate }} ogni 100 inviati</div>
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

        {{-- Link più cliccati (aggregato: nessun dato sulle persone) --}}
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between">
                    <span>Link più cliccati</span>
                    <span class="badge bg-info">{{ number_format($totalClicks) }} click</span>
                </div>
                @if($topLinks->isEmpty())
                    <div class="card-body text-muted small">Nessun click registrato.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Link</th>
                                    <th class="text-end">Click</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topLinks as $link)
                                    <tr>
                                        <td class="small text-truncate" style="max-width:380px;">
                                            <a href="{{ $link->original_url }}" target="_blank" rel="noopener noreferrer" title="{{ $link->original_url }}">
                                                {{ $link->original_url }}
                                            </a>
                                        </td>
                                        <td class="small text-end">{{ number_format($link->total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Nota sul tracciamento anonimo --}}
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">Statistiche anonime</div>
                <div class="card-body small text-muted">
                    Pixel di apertura e link sono <strong>identici per tutti i destinatari</strong> della campagna: SendMail
                    conta quante aperture e quanti click ci sono stati (con l'orario all'ora), ma <strong>non registra chi</strong>
                    ha aperto o cliccato, né indirizzo IP o dispositivo. Per questo non ci sono aperture o click «unici».
                    Le aperture sono un dato indicativo: includono riaperture e caricamenti automatici di alcuni programmi di posta.
                </div>
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
