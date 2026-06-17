<x-app-layout>
    <x-slot name="title">Campagne</x-slot>
    <x-slot name="actions">
        <a href="{{ route('campaigns.create') }}" class="btn btn-primary btn-sm">+ Nuova campagna</a>
    </x-slot>

    @if($campaigns->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-2">Nessuna campagna ancora.</p>
            <a href="{{ route('campaigns.create') }}" class="btn btn-primary btn-sm">Crea la prima campagna</a>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Oggetto</th>
                            <th>Lista</th>
                            <th>Stato</th>
                            <th>Destinatari</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                            <tr>
                                <td>
                                    <a href="{{ route('campaigns.edit', $campaign) }}" class="fw-semibold text-decoration-none">
                                        {{ $campaign->subject }}
                                    </a>
                                </td>
                                <td class="small text-muted">{{ $campaign->list_names }}</td>
                                <td>
                                    @php $badge = match($campaign->status) {
                                        'draft'     => ['secondary', 'Bozza'],
                                        'scheduled' => ['info',      'Programmata'],
                                        'sending'   => ['warning',   'In invio'],
                                        'sent'      => ['success',   'Inviata'],
                                        'paused'    => ['dark',      'In pausa'],
                                        default     => ['secondary', $campaign->status],
                                    }; @endphp
                                    <span class="badge bg-{{ $badge[0] }}">{{ $badge[1] }}</span>
                                </td>
                                <td>{{ number_format($campaign->total_recipients) }}</td>
                                <td class="small text-muted">
                                    {{ ($campaign->sent_at ?? $campaign->scheduled_at ?? $campaign->created_at)->format('d/m/Y') }}
                                </td>
                                <td class="text-end">
                                    @if($campaign->isDraft())
                                        <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-secondary">Modifica</a>
                                    @else
                                        <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-secondary">Vedi</a>
                                        <a href="{{ route('reports.show', $campaign) }}" class="btn btn-sm btn-outline-success">Report</a>
                                    @endif

                                    <form method="POST" action="{{ route('campaigns.duplicate', $campaign) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Duplica</button>
                                    </form>

                                    @if($campaign->isDraft())
                                        <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" class="d-inline"
                                              onsubmit="return confirm('Eliminare questa campagna?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Elimina</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</x-app-layout>
