@php
    $monthNames = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    $int = fn ($v) => number_format($v, 0, ',', '.');
    $dec = fn ($v, $d = 2) => number_format($v, $d, ',', '.');
    $year = $data['year'];
    $months = $data['months'];
@endphp
<x-app-layout>
    <x-slot name="title">Fatturazione</x-slot>

    <x-slot name="actions">
        <form method="GET" action="{{ route('billing.index') }}" class="d-flex align-items-center gap-2">
            <label for="anno" class="small text-muted mb-0">Anno</label>
            <select name="anno" id="anno" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </x-slot>

    @if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    {{-- Cifre dell'anno e ipotesi di calcolo --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted">Email inviate nel {{ $year }}</div>
                <div class="fs-3 fw-semibold">{{ $int($data['totalEmails']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted">Costo stimato nel {{ $year }}</div>
                <div class="fs-3 fw-semibold">€ {{ $dec($data['totalEur']) }}</div>
                <div class="small text-muted">$ {{ $dec($data['totalUsd']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="small text-muted">Tariffa e cambio</div>
                    <a class="small" data-bs-toggle="collapse" href="#billing-settings" role="button" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="billing-settings">Modifica</a>
                </div>
                <div class="fw-semibold">$ {{ $dec($usdPer1000) }} ogni 1.000 email</div>
                <div class="small text-muted">
                    1 € = $ {{ $dec($usdPerEur, 4) }}
                    ({{ $rateSource }}, {{ \Carbon\Carbon::parse($rateDate)->format('d/m/Y') }})
                </div>
            </div></div>
        </div>
    </div>

    <div class="collapse {{ $errors->any() ? 'show' : '' }} mb-3" id="billing-settings">
        <div class="card"><div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <form method="POST" action="{{ route('billing.settings') }}" class="d-flex flex-wrap gap-3 align-items-end">
                @csrf @method('PUT')
                <input type="hidden" name="anno" value="{{ $year }}">
                <div>
                    <label class="form-label small fw-semibold mb-1">Costo per 1.000 email (USD)</label>
                    <input type="number" name="usd_per_1000" step="0.0001" min="0" value="{{ old('usd_per_1000', $usdPer1000) }}"
                           class="form-control form-control-sm" style="width: 150px">
                </div>
                <div>
                    <label class="form-label small fw-semibold mb-1">Cambio (USD per 1 €)</label>
                    <input type="number" name="usd_per_eur" step="0.0001" min="0.0001" value="{{ old('usd_per_eur', $usdPerEur) }}"
                           class="form-control form-control-sm" style="width: 150px">
                </div>
                <button class="btn btn-outline-secondary btn-sm">Salva</button>
            </form>
            <form method="POST" action="{{ route('billing.refresh-rate') }}" class="ms-auto">
                @csrf
                <button class="btn btn-primary btn-sm">Aggiorna il cambio dalla BCE</button>
            </form>
        </div></div>
    </div>

    {{-- Istogramma: email inviate per mese, una serie per cliente --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-1">Email inviate per mese e per cliente</div>
            <div class="small text-muted mb-3">In ogni mese una colonna per cliente (profilo di invio), ciascuno col suo colore. Passa sopra un mese per il dettaglio e il costo.</div>
            @if($data['totalEmails'] === 0)
                <div class="text-center text-muted py-5">Nessuna email inviata nel {{ $year }}.</div>
            @else
                <div style="position: relative; height: 360px;">
                    <canvas id="billingChart" role="img"
                            aria-label="Istogramma delle email inviate ogni mese del {{ $year }}, con una colonna per cliente. I valori sono nella tabella sotto."></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Totali mensili e costo --}}
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Mese</th>
                        <th class="text-end">Email inviate</th>
                        <th class="text-end">Costo del mese ($)</th>
                        <th class="text-end">Costo del mese (€)</th>
                        <th class="text-end">Costo cumulato (€)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($months as $m)
                        <tr class="{{ $m['future'] ? 'text-muted' : '' }}">
                            <td>{{ $monthNames[$m['month']] }}</td>
                            @if($m['future'])
                                <td class="text-end">—</td><td class="text-end">—</td><td class="text-end">—</td><td class="text-end">—</td>
                            @else
                                <td class="text-end">{{ $int($m['emails']) }}</td>
                                <td class="text-end">{{ $dec($m['usd']) }}</td>
                                <td class="text-end">{{ $dec($m['eur']) }}</td>
                                <td class="text-end fw-semibold">{{ $dec($m['cumEur']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td>Totale {{ $year }}</td>
                        <td class="text-end">{{ $int($data['totalEmails']) }}</td>
                        <td class="text-end">{{ $dec($data['totalUsd']) }}</td>
                        <td class="text-end">{{ $dec($data['totalEur']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Totali per cliente --}}
    @if(count($data['clients']))
    <div class="card mb-3">
        <div class="card-header fw-semibold">Per cliente nel {{ $year }}</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th class="text-end">Email inviate</th>
                        <th class="text-end">Quota</th>
                        <th class="text-end">Costo ($)</th>
                        <th class="text-end">Costo (€)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['clients'] as $c)
                        <tr>
                            <td>
                                <span class="d-inline-block rounded-1 me-2 align-middle" style="width:10px;height:10px;background:{{ $c['color'] }}"></span>{{ $c['name'] }}
                            </td>
                            <td class="text-end">{{ $int($c['emails']) }}</td>
                            <td class="text-end">{{ $dec($c['emails'] / $data['totalEmails'] * 100, 1) }}%</td>
                            <td class="text-end">{{ $dec($c['usd']) }}</td>
                            <td class="text-end">{{ $dec($c['eur']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <p class="text-muted small">
        Stima: email inviate (stato «sent») × tariffa, convertita con un unico cambio per tutti i mesi.
        I mesi sono calcolati in UTC, come la fatturazione di AWS. Le email di test non sono incluse (non vengono registrate tra gli invii).
    </p>

    @if($data['totalEmails'] > 0)
    @push('scripts')
    <script>
    (function () {
        var labels  = ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
        var full    = @json(array_slice($monthNames, 1));
        var series  = @json($data['series']);
        var monthEur = @json(array_map(fn ($m) => round($m['eur'], 2), array_values($months)));
        // 'always': in it-IT i numeri a 4 cifre altrimenti non avrebbero il punto delle migliaia (la tabella sì)
        var nf  = new Intl.NumberFormat('it-IT', { useGrouping: 'always' });
        var eur = new Intl.NumberFormat('it-IT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 });
        var fmt = function (v) { return nf.format(Math.round(v)); };
        var canvas  = document.getElementById('billingChart');
        var surface = getComputedStyle(canvas.closest('.card')).backgroundColor;

        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

        // Colonne affiancate: in ogni mese una per cliente, nell'ordine e coi colori della legenda
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: series.map(function (s) {
                    return {
                        label: s.name,
                        data: s.data,
                        backgroundColor: s.color,
                        borderColor: surface,         // 1px per lato: 2px di spazio tra le colonne affiancate
                        borderWidth: 1,
                        borderSkipped: false,
                        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
                        maxBarThickness: 24,
                        categoryPercentage: 0.8,
                        barPercentage: 1
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, border: { color: '#c3c2b7' }, ticks: { color: '#898781' } },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e1e0d9' }, border: { display: false },
                        ticks: { color: '#898781', callback: function (v) { return nf.format(v); } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        onClick: null,   // il totale e il costo del mese nel tooltip riguardano tutti i clienti
                        labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10, padding: 16, color: '#52514e' }
                    },
                    tooltip: {
                        itemSort: function (a, b) { return a.datasetIndex - b.datasetIndex; },
                        filter: function (item) { return item.parsed.y > 0; },
                        callbacks: {
                            title: function (items) { return full[items[0].dataIndex]; },
                            label: function (item) { return ' ' + item.dataset.label + ': ' + fmt(item.parsed.y); },
                            footer: function (items) {
                                var j = items[0].dataIndex, sum = 0;
                                series.forEach(function (s) { sum += s.data[j]; });
                                return ['Totale: ' + fmt(sum) + ' email', 'Costo del mese: € ' + eur.format(monthEur[j])];
                            }
                        }
                    }
                }
            }
        });
    })();
    </script>
    @endpush
    @endif

</x-app-layout>
