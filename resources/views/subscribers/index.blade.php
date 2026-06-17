<x-app-layout>
    <x-slot name="title">{{ $list->name }} — Iscritti</x-slot>
    <x-slot name="actions">
        <div class="d-flex gap-2">
            <a href="{{ route('lists.subscribers.export', $list) }}" class="btn btn-outline-secondary btn-sm">
                Esporta CSV
            </a>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                Importa CSV
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddSubscriber">
                + Aggiungi iscritto
            </button>
        </div>
    </x-slot>

    {{-- Filtri --}}
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Cerca per nome, cognome, azienda, email..."
                   value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Tutti gli stati</option>
                <option value="subscribed"   {{ request('status') === 'subscribed'   ? 'selected' : '' }}>Iscritti</option>
                <option value="unsubscribed" {{ request('status') === 'unsubscribed' ? 'selected' : '' }}>Disiscritti</option>
                <option value="bounced"      {{ request('status') === 'bounced'      ? 'selected' : '' }}>Bounced</option>
                <option value="complained"   {{ request('status') === 'complained'   ? 'selected' : '' }}>Complained</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            @if(request('search') || request('status'))
                <a href="{{ route('lists.subscribers.index', $list) }}" class="btn btn-link btn-sm">Reset</a>
            @endif
        </div>
    </form>

    {{-- Tabella con gestione selezione --}}
    <div x-data="{
        selected: [],
        allIds: {{ $subscribers->pluck('id')->toJson() }},
        get allSelected() { return this.allIds.length > 0 && this.allIds.every(id => this.selected.includes(id)); },
        toggleAll() {
            if (this.allSelected) { this.selected = []; }
            else { this.selected = [...this.allIds]; }
        }
    }">

        {{-- Barra azioni bulk --}}
        <div x-show="selected.length > 0"
             x-transition
             class="alert alert-primary d-flex align-items-center gap-3 mb-3 py-2">
            <span class="fw-semibold" x-text="selected.length + ' selezionati'"></span>
            <button type="button" class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#modalBulkStatus">
                Cambia stato
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#modalBulkDomain">
                Cambia dominio
            </button>
            <button type="button" class="btn btn-sm btn-danger"
                    data-bs-toggle="modal" data-bs-target="#modalBulkBlacklist">
                Blacklist
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#modalBulkDelete">
                Elimina
            </button>
            <button type="button" class="btn btn-sm btn-link text-muted ms-auto"
                    @click="selected = []">
                Deseleziona tutto
            </button>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input"
                                       :checked="allSelected"
                                       @change="toggleAll()">
                            </th>
                            <th>Email</th>
                            <th>Nome</th>
                            <th>Cognome</th>
                            <th>Azienda</th>
                            <th>Stato</th>
                            <th>Iscritto il</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscribers as $subscriber)
                            @php $isBlacklisted = isset($blacklistedEmails[strtolower($subscriber->email)]); @endphp
                            <tr :class="selected.includes({{ $subscriber->id }}) ? 'table-active' : ''"
                                @if($isBlacklisted) style="opacity: .5;" @endif>
                                <td>
                                    <input type="checkbox" class="form-check-input"
                                           :checked="selected.includes({{ $subscriber->id }})"
                                           @change="
                                               selected.includes({{ $subscriber->id }})
                                                   ? selected = selected.filter(i => i !== {{ $subscriber->id }})
                                                   : selected.push({{ $subscriber->id }})
                                           ">
                                </td>
                                <td>
                                    @if($isBlacklisted)
                                        <span class="text-decoration-line-through text-muted">{{ $subscriber->email }}</span>
                                        <span class="badge bg-dark ms-1" title="In blacklist">⛔</span>
                                    @else
                                        {{ $subscriber->email }}
                                    @endif
                                </td>
                                <td @if($isBlacklisted) class="text-decoration-line-through text-muted" @endif>{{ $subscriber->first_name }}</td>
                                <td @if($isBlacklisted) class="text-decoration-line-through text-muted" @endif>{{ $subscriber->last_name }}</td>
                                <td @if($isBlacklisted) class="text-decoration-line-through text-muted" @endif>{{ $subscriber->company }}</td>
                                <td>
                                    @php $badge = match($subscriber->status) {
                                        'subscribed'   => 'success',
                                        'unsubscribed' => 'secondary',
                                        'bounced'      => 'warning',
                                        'complained'   => 'danger',
                                        default        => 'secondary',
                                    }; @endphp
                                    <span class="badge bg-{{ $badge }}">{{ $subscriber->status }}</span>
                                </td>
                                <td class="small text-muted">{{ $subscriber->subscribed_at?->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    @if(!$isBlacklisted)
                                        <button class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEdit{{ $subscriber->id }}">
                                            Modifica
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalBlacklist{{ $subscriber->id }}">
                                            Blacklist
                                        </button>
                                    @else
                                        <a href="{{ route('blacklist.index') }}" class="btn btn-sm btn-outline-secondary">
                                            Vedi blacklist
                                        </a>
                                    @endif
                                    <form method="POST"
                                          action="{{ route('lists.subscribers.destroy', [$list, $subscriber]) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Eliminare questo iscritto?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Nessun iscritto trovato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($subscribers->hasPages())
                <div class="card-footer">
                    {{ $subscribers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>

        {{-- Form bulk nascosto, compilato da Alpine prima del submit --}}
        <form id="bulkForm" method="POST" action="{{ route('lists.subscribers.bulk', $list) }}">
            @csrf
            <input type="hidden" name="action" id="bulkAction">
            <input type="hidden" name="new_status" id="bulkNewStatus">
            <input type="hidden" name="old_domain" id="bulkOldDomain">
            <input type="hidden" name="new_domain" id="bulkNewDomain">
            <input type="hidden" name="bulk_reason" id="bulkReason">
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>

    </div>{{-- fine x-data --}}

    {{-- Modal blacklist singolo --}}
    @foreach($subscribers as $subscriber)
        @if(!isset($blacklistedEmails[strtolower($subscriber->email)]))
            <div class="modal fade" id="modalBlacklist{{ $subscriber->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('blacklist.store') }}" class="modal-content">
                        @csrf
                        <input type="hidden" name="subscriber_id" value="{{ $subscriber->id }}">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Aggiungi in blacklist</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Stai per aggiungere <strong>{{ $subscriber->email }}</strong> alla blacklist globale.</p>
                            <p class="small text-muted">L'email non potrà più ricevere comunicazioni da nessuna lista. Per rimuoverla dovrai accedere alla sezione Blacklist.</p>
                            <div class="mb-3">
                                <label class="form-label">Motivo <span class="text-muted">(opzionale)</span></label>
                                <input type="text" name="reason" class="form-control" placeholder="es. richiesta utente, spam complaint...">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="submit" class="btn btn-danger">Aggiungi in blacklist</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    {{-- Modal modifica singolo — fuori dal div Alpine --}}
    @foreach($subscribers as $subscriber)
        <div class="modal fade" id="modalEdit{{ $subscriber->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST"
                      action="{{ route('lists.subscribers.update', [$list, $subscriber]) }}"
                      class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Modifica iscritto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @include('subscribers._form', ['subscriber' => $subscriber])
                        <div class="mb-3">
                            <label class="form-label">Stato</label>
                            <select name="status" class="form-select">
                                @foreach(['subscribed','unsubscribed','bounced','complained'] as $s)
                                    <option value="{{ $s }}" {{ $subscriber->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    {{-- Modal bulk: cambia stato --}}
    <div class="modal fade" id="modalBulkStatus" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambia stato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nuovo stato</label>
                    <select id="selectNewStatus" class="form-select">
                        <option value="subscribed">subscribed</option>
                        <option value="unsubscribed">unsubscribed</option>
                        <option value="bounced">bounced</option>
                        <option value="complained">complained</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="submitBulk('status')">Applica</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal bulk: cambia dominio --}}
    <div class="modal fade" id="modalBulkDomain" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambia dominio email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Sostituisce il dominio nelle email selezionate.</p>
                    <div class="mb-3">
                        <label class="form-label">Dominio attuale</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" id="inputOldDomain" class="form-control" placeholder="esempio.it">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nuovo dominio</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" id="inputNewDomain" class="form-control" placeholder="esempio.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="submitBulk('domain')">Applica</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal bulk: blacklist --}}
    <div class="modal fade" id="modalBulkBlacklist" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Aggiungi in blacklist</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Stai per aggiungere in blacklist tutti gli iscritti selezionati. Non potranno più ricevere comunicazioni da nessuna lista.</p>
                    <div class="mb-3">
                        <label class="form-label">Motivo <span class="text-muted">(opzionale)</span></label>
                        <input type="text" id="inputBulkReason" class="form-control"
                               placeholder="es. richiesta utente, spam complaint...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-danger" onclick="submitBulk('blacklist')">Aggiungi in blacklist</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal bulk: elimina --}}
    <div class="modal fade" id="modalBulkDelete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Elimina iscritti selezionati</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Stai per eliminare definitivamente gli iscritti selezionati. L'operazione non è reversibile.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-danger" onclick="submitBulk('delete')">Elimina</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal aggiungi --}}
    <div class="modal fade" id="modalAddSubscriber" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('lists.subscribers.store', $list) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi iscritto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('subscribers._form', ['subscriber' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Aggiungi</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal import --}}
    <div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('lists.subscribers.import', $list) }}"
                  enctype="multipart/form-data" class="modal-content"
                  x-data="{ mode: 'file' }">
                @csrf
                <input type="hidden" name="import_mode" :value="mode">
                <div class="modal-header">
                    <h5 class="modal-title">Importa iscritti</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <button type="button" class="nav-link" :class="mode === 'file' ? 'active' : ''"
                                    @click="mode = 'file'">File CSV</button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" :class="mode === 'paste' ? 'active' : ''"
                                    @click="mode = 'paste'">Incolla testo</button>
                        </li>
                    </ul>
                    <div x-show="mode === 'file'">
                        <p class="small text-muted">Colonne: <code>email</code>, <code>first_name</code> (o <code>nome</code>), <code>last_name</code> (o <code>cognome</code>), <code>company</code> (o <code>azienda</code>). Separatore: virgola o punto e virgola.</p>
                        <input type="file" name="csv" class="form-control" accept=".csv,.txt">
                    </div>
                    <div x-show="mode === 'paste'">
                        <p class="small text-muted">
                            Incolla da Excel (tab) o con separatore punto e virgola.<br>
                            Con intestazione: <code>email; nome; cognome; azienda</code><br>
                            Senza intestazione: colonne in ordine email, nome, cognome, azienda.
                        </p>
                        <textarea name="paste_text" class="form-control font-monospace"
                                  rows="10" placeholder="Incolla qui il testo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Importa</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function submitBulk(action) {
        document.getElementById('bulkAction').value = action;
        if (action === 'status') {
            document.getElementById('bulkNewStatus').value = document.getElementById('selectNewStatus').value;
        }
        if (action === 'domain') {
            document.getElementById('bulkOldDomain').value = document.getElementById('inputOldDomain').value;
            document.getElementById('bulkNewDomain').value = document.getElementById('inputNewDomain').value;
        }
        if (action === 'blacklist') {
            document.getElementById('bulkReason').value = document.getElementById('inputBulkReason').value;
        }
        document.getElementById('bulkForm').submit();
    }
    </script>

</x-app-layout>
