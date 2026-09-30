<x-app-layout>
    <x-slot name="title">Profili di invio</x-slot>
    <x-slot name="actions">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateProfile">
            + Nuovo profilo
        </button>
    </x-slot>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <p class="text-muted small">
        Un profilo raccoglie mittente, reply-to e Configuration Set SES. Si sceglie nella campagna e precompila i campi mittente.
    </p>

    @if($profiles->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-2">Nessun profilo di invio.</p>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateProfile">
                Crea il primo profilo
            </button>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Profilo</th>
                            <th>Mittente</th>
                            <th>Reply-to</th>
                            <th>Configuration Set</th>
                            <th>Lista test</th>
                            <th>Campagne</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($profiles as $profile)
                            <tr>
                                <td class="fw-semibold">{{ $profile->name }}</td>
                                <td>
                                    <div>{{ $profile->from_name }}</div>
                                    <div class="small text-muted">{{ $profile->from_email }}</div>
                                </td>
                                <td class="small">{{ $profile->reply_to ?: '—' }}</td>
                                <td class="small">
                                    @if($profile->configuration_set)
                                        <code>{{ $profile->configuration_set }}</code>
                                    @else
                                        <span class="text-muted">globale</span>
                                    @endif
                                </td>
                                <td class="small">{{ $profile->testList?->name ?? '—' }}</td>
                                <td>{{ $profile->campaigns_count }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                            data-bs-target="#modalEditProfile{{ $profile->id }}">Modifica</button>
                                    <form method="POST" action="{{ route('sender-profiles.destroy', $profile) }}" class="d-inline"
                                          onsubmit="return confirm('Eliminare il profilo «{{ $profile->name }}»?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Crea --}}
    <div class="modal fade" id="modalCreateProfile" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('sender-profiles.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuovo profilo di invio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">@include('sender-profiles._fields')</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modifica --}}
    @foreach($profiles as $profile)
        <div class="modal fade" id="modalEditProfile{{ $profile->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('sender-profiles.update', $profile) }}" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Modifica «{{ $profile->name }}»</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">@include('sender-profiles._fields', ['profile' => $profile])</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</x-app-layout>
