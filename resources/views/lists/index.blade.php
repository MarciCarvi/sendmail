<x-app-layout>
    <x-slot name="title">Liste</x-slot>
    <x-slot name="actions">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateList">
            + Nuova lista
        </button>
    </x-slot>

    @if($lists->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-2">Nessuna lista ancora.</p>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateList">
                Crea la prima lista
            </button>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Mittente</th>
                            <th>Iscritti</th>
                            <th>Double opt-in</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lists as $list)
                            <tr>
                                <td>
                                    <a href="{{ route('lists.subscribers.index', $list) }}" class="fw-semibold text-decoration-none">
                                        {{ $list->name }}
                                    </a>
                                </td>
                                <td>
                                    <div>{{ $list->from_name }}</div>
                                    <div class="small text-muted">{{ $list->from_email }}</div>
                                </td>
                                <td>{{ number_format($list->subscribers_count) }}</td>
                                <td>
                                    @if($list->double_optin)
                                        <span class="badge bg-success">Sì</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalForm{{ $list->id }}">
                                        Form
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditList{{ $list->id }}">
                                        Modifica
                                    </button>
                                    <form method="POST" action="{{ route('lists.destroy', $list) }}" class="d-inline"
                                          onsubmit="return confirm('Eliminare la lista e tutti i suoi iscritti?')">
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

    {{-- Modal modifica — fuori dalla tabella --}}
    @foreach($lists as $list)
        <div class="modal fade" id="modalEditList{{ $list->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('lists.update', $list) }}" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Modifica lista</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @include('lists._form', ['list' => $list])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    {{-- Modal embed form --}}
    @foreach($lists as $list)
    @php
        $appUrl    = rtrim(config('app.url'), '/');
        $formUrl   = $appUrl . '/embed/' . $list->api_token;
        $submitUrl = $appUrl . '/subscribe/' . $list->api_token;
        $jsSnippet = '<div id="sm-' . $list->id . '"></div>' . "\n" .
                     '<script src="' . $appUrl . '/embed.js"' . "\n" .
                     '  data-token="' . $list->api_token . '"' . "\n" .
                     '  data-target="sm-' . $list->id . '"' . "\n" .
                     '  data-url="' . $appUrl . '"></script>';
        $iframeSnippet = '<iframe src="' . $formUrl . '"' . "\n" .
                         '  width="100%" height="380" frameborder="0"' . "\n" .
                         '  style="border:none;border-radius:8px"></iframe>';
        $htmlSnippet = '<form action="' . $submitUrl . '" method="POST">' . "\n" .
                       '  <input type="email" name="email" placeholder="Email" required><br>' . "\n" .
                       '  <input type="text" name="first_name" placeholder="Nome"><br>' . "\n" .
                       '  <input type="text" name="last_name" placeholder="Cognome"><br>' . "\n" .
                       '  <input type="text" name="company" placeholder="Azienda"><br>' . "\n" .
                       '  <button type="submit">Iscriviti</button>' . "\n" .
                       '</form>';
    @endphp
    <div class="modal fade" id="modalForm{{ $list->id }}" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Form di iscrizione — {{ $list->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Anteprima --}}
                    <div class="mb-4 text-center">
                        <a href="{{ $formUrl }}" target="_blank" class="btn btn-outline-primary btn-sm">
                            Apri anteprima del form ↗
                        </a>
                    </div>

                    <ul class="nav nav-tabs mb-3" id="embedTabs{{ $list->id }}" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#tab-js-{{ $list->id }}">JS Embed</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#tab-iframe-{{ $list->id }}">Iframe</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#tab-html-{{ $list->id }}">HTML puro</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-js-{{ $list->id }}">
                            <p class="small text-muted">Incolla nel tuo sito dove vuoi che appaia il form. Nessuna dipendenza richiesta.</p>
                            <div class="position-relative">
                                <pre class="bg-light border rounded p-3 small" style="overflow-x:auto;white-space:pre-wrap">{{ $jsSnippet }}</pre>
                                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2"
                                        onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent);this.textContent='Copiato!'">
                                    Copia
                                </button>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-iframe-{{ $list->id }}">
                            <p class="small text-muted">Il form è ospitato su questo server. Zero conflitti CSS con il tuo sito.</p>
                            <div class="position-relative">
                                <pre class="bg-light border rounded p-3 small" style="overflow-x:auto;white-space:pre-wrap">{{ $iframeSnippet }}</pre>
                                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2"
                                        onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent);this.textContent='Copiato!'">
                                    Copia
                                </button>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-html-{{ $list->id }}">
                            <p class="small text-muted">HTML grezzo da personalizzare completamente. Aggiungi i tuoi stili.</p>
                            <div class="position-relative">
                                <pre class="bg-light border rounded p-3 small" style="overflow-x:auto;white-space:pre-wrap">{{ $htmlSnippet }}</pre>
                                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2"
                                        onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent);this.textContent='Copiato!'">
                                    Copia
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    {{-- Modal crea --}}
    <div class="modal fade" id="modalCreateList" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('lists.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuova lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('lists._form', ['list' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea</button>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>
