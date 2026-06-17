<x-app-layout>
    <x-slot name="title">Blacklist</x-slot>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Cerca per email..."
                   value="{{ request('search') }}">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if(request('search'))
                <a href="{{ route('blacklist.index') }}" class="btn btn-link btn-sm">Reset</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Liste di appartenenza</th>
                        <th>Motivo</th>
                        <th>Aggiunto il</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="fw-semibold">{{ $entry->email }}</td>
                            <td>
                                @if($entry->list_ids)
                                    @foreach($entry->list_ids as $l)
                                        <span class="badge bg-secondary me-1">{{ $l['name'] }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $entry->reason ?: '—' }}</td>
                            <td class="small text-muted">{{ $entry->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('blacklist.destroy', $entry) }}"
                                      onsubmit="return confirm('Rimuovere «{{ $entry->email }}» dalla blacklist?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Rimuovi</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Blacklist vuota.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="card-footer">
                {{ $entries->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

</x-app-layout>
