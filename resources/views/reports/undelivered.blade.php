<x-app-layout>
    <x-slot name="title">Non consegnati — {{ $campaign->subject }}</x-slot>
    <x-slot name="actions">
        <div class="d-flex gap-2">
            <a href="{{ route('reports.undelivered.export', $campaign) }}" class="btn btn-outline-secondary btn-sm">Esporta CSV</a>
            <a href="{{ route('reports.show', $campaign) }}" class="btn btn-outline-secondary btn-sm">← Report</a>
        </div>
    </x-slot>

    <p class="text-muted small">
        Invii accettati da SES che non risultano consegnati, oppure per cui è arrivato un bounce.
        Il motivo viene dagli eventi SES registrati.
    </p>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($summary as $label => $n)
            <span class="badge text-bg-{{ $n > 0 ? 'light border' : 'light' }} fs-6 fw-normal">{{ $label }}: <strong>{{ number_format($n) }}</strong></span>
        @endforeach
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cerca per email..." value="{{ request('search') }}">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if(request('search'))<a href="{{ route('reports.undelivered', $campaign) }}" class="btn btn-link btn-sm">Reset</a>@endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Email</th><th>Dominio</th><th>Inviato il</th><th>Motivo</th></tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['email'] }}</td>
                            <td class="small">{{ $row['domain'] }}</td>
                            <td class="small text-muted">{{ $row['sent_at']?->format('d/m/Y H:i') }}</td>
                            <td class="small {{ $row['bounced'] ? 'text-danger' : 'text-muted' }}">{{ $row['reason'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Nessun invio non consegnato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sends->links() }}</div>
</x-app-layout>
