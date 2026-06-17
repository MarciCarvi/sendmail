<x-app-layout>
    <x-slot name="title">Report</x-slot>

    @if($campaigns->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-2">Nessuna campagna inviata ancora.</p>
            <a href="{{ route('campaigns.index') }}" class="btn btn-primary btn-sm">Vai alle campagne</a>
        </div>
    @else
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
