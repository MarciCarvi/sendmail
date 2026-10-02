<x-app-layout>
    <x-slot name="title">Disiscritti</x-slot>

    <p class="text-muted small">
        Chi si disiscrive da una lista non riceve più campagne con un mittente dello stesso <strong>dominio</strong>
        (il cliente), ma può continuare a ricevere quelle di altri clienti. Per bloccare un indirizzo per tutti usa la
        <a href="{{ route('blacklist.index') }}">Blacklist</a>.
    </p>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Cerca per email..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <select name="domain" class="form-select form-select-sm">
                <option value="">Tutti i clienti</option>
                @foreach($domains as $d)
                    <option value="{{ $d }}" @selected(request('domain') === $d)>
                        {{ $d }}@if(!empty($clientNames[$d])) — {{ $clientNames[$d] }}@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if(request('search') || request('domain'))
                <a href="{{ route('unsubscribes.index') }}" class="btn btn-link btn-sm">Reset</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Cliente</th>
                        <th>Lista di origine</th>
                        <th>Disiscritto il</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="fw-semibold">{{ $entry->email }}</td>
                            <td>
                                <div>{{ $entry->sender_domain }}</div>
                                @if(!empty($clientNames[$entry->sender_domain]))
                                    <div class="small text-muted">{{ $clientNames[$entry->sender_domain] }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $entry->list?->name ?? '— (lista eliminata)' }}</td>
                            <td class="small text-muted">{{ $entry->unsubscribed_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('unsubscribes.destroy', $entry) }}"
                                      onsubmit="return confirm('Riabilitare «{{ $entry->email }}» per {{ $entry->sender_domain }}? Tornerà a ricevere le campagne di questo cliente.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Riabilita</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nessun disiscritto.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
</x-app-layout>
