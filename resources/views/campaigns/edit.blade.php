<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($campaign) ? 'Modifica campagna' : 'Nuova campagna' }} — {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body { background: #f8f9fa; }
        #unlayer-editor { height: calc(100vh - 220px); min-height: 500px; }
        .sidebar-fixed { width: 240px; flex-shrink: 0; }
    </style>
</head>
<body>

{{-- Top bar --}}
<nav class="navbar navbar-expand navbar-dark bg-dark px-3 py-2">
    <a href="{{ route('campaigns.index') }}" class="text-white text-decoration-none me-3">← Campagne</a>
    <span class="text-white fw-semibold">
        {{ isset($campaign) ? $campaign->subject : 'Nuova campagna' }}
    </span>
    @if(isset($campaign) && !$campaign->isDraft())
        <span class="badge bg-success ms-2">{{ ucfirst($campaign->status) }}</span>
    @endif
</nav>

@if(session('success'))
    <div id="flash-success" class="alert alert-success alert-dismissible fade show mb-0 rounded-0" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div id="flash-error" class="alert alert-danger alert-dismissible fade show mb-0 rounded-0" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
<script>
    document.addEventListener('DOMContentLoaded', function () {
        ['flash-success', 'flash-error'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            setTimeout(function () {
                el.classList.remove('show');
                el.addEventListener('transitionend', function () { el.remove(); }, { once: true });
            }, 3000);
        });
    });
</script>

<div x-data="campaignEditor()" class="d-flex" style="height: calc(100vh - 56px);">

    {{-- Pannello sinistro: impostazioni --}}
    <div class="sidebar-fixed bg-white border-end d-flex flex-column overflow-auto p-3">
        <form id="campaignForm"
              method="POST"
              action="{{ isset($campaign) ? route('campaigns.update', $campaign) : route('campaigns.store') }}">
            @csrf
            @if(isset($campaign)) @method('PUT') @endif

            {{-- Campi nascosti compilati da Alpine --}}
            <input type="hidden" name="html_content" x-model="htmlContent">
            <input type="hidden" name="design_json" x-model="designJson">
            <input type="hidden" name="text_content" x-model="textContent">

            <div class="mb-3">
                <label class="form-label fw-semibold small">Liste destinatari</label>
                @php
                    $selectedListIds = old('list_ids', isset($campaign) ? $campaign->lists->pluck('id')->toArray() : []);
                @endphp
                <select name="list_ids[]" multiple class="form-select form-select-sm" style="min-height: 100px;">
                    @foreach($lists as $list)
                        <option value="{{ $list->id }}"
                            {{ in_array($list->id, $selectedListIds) ? 'selected' : '' }}>
                            {{ $list->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Tieni premuto <kbd>Ctrl</kbd> (o <kbd>⌘</kbd>) per selezionare più liste.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Oggetto</label>
                <input type="text" name="subject" class="form-control form-control-sm @error('subject') is-invalid @enderror"
                       value="{{ old('subject', $campaign->subject ?? '') }}" placeholder="Oggetto dell'email">
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Variabili: <code>@{{first_name}}</code> <code>@{{last_name}}</code> <code>@{{full_name}}</code> <code>@{{company}}</code> <code>@{{email}}</code></div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Nome mittente</label>
                <input type="text" name="from_name" class="form-control form-control-sm @error('from_name') is-invalid @enderror"
                       value="{{ old('from_name', $campaign->from_name ?? $defaults['from_name'] ?? '') }}">
                @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Email mittente</label>
                <input type="email" name="from_email" class="form-control form-control-sm @error('from_email') is-invalid @enderror"
                       value="{{ old('from_email', $campaign->from_email ?? $defaults['from_email'] ?? '') }}">
                @error('from_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Reply-to <span class="text-muted">(opzionale)</span></label>
                <input type="email" name="reply_to" class="form-control form-control-sm"
                       value="{{ old('reply_to', $campaign->reply_to ?? '') }}">
            </div>

            @if(!isset($campaign) || $campaign->isDraft())
                <button type="button" class="btn btn-primary btn-sm w-100 mb-2" @click="save()">
                    Salva bozza
                </button>
            @endif

        </form>{{-- fine campaignForm --}}

        {{-- Invio di test --}}
        @if(isset($campaign))
                <hr class="my-2">
                <div x-data="{ testEmail: '', loading: false, result: null, ok: null }">
                    <label class="form-label fw-semibold small">Email di test</label>
                    <input type="email" x-model="testEmail" class="form-control form-control-sm mb-2"
                           placeholder="tuaemail@esempio.com">
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100"
                            :disabled="loading || !testEmail"
                            @click="
                                loading = true; result = null;
                                fetch('{{ route('campaigns.send-test', $campaign) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                    },
                                    body: JSON.stringify({ test_email: testEmail })
                                })
                                .then(r => r.json())
                                .then(d => { result = d.message; ok = d.success; loading = false; })
                                .catch(() => { result = 'Errore'; ok = false; loading = false; })
                            ">
                        <span x-show="!loading">Invia test</span>
                        <span x-show="loading">Invio...</span>
                    </button>
                    <div x-show="result" class="mt-1 small" :class="ok ? 'text-success' : 'text-danger'" x-text="result"></div>
                </div>
            @endif

            {{-- Azioni invio --}}
            @if(isset($campaign) && $campaign->isDraft())
                <hr class="my-2">
                <p class="small fw-semibold mb-2">Invia campagna</p>

                <form method="POST" action="{{ route('campaigns.send-now', $campaign) }}"
                      onsubmit="return confirm('Inviare subito la campagna a tutti gli iscritti delle liste selezionate?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm w-100 mb-2">
                        ▶ Invia ora
                    </button>
                </form>

                <div x-data="{ open: false, scheduledAt: '' }">
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-1"
                            @click="open = !open">
                        🗓 Programma invio
                    </button>
                    <div x-show="open" x-cloak class="mt-2">
                        <form method="POST" action="{{ route('campaigns.schedule', $campaign) }}">
                            @csrf
                            <input type="datetime-local" name="scheduled_at" x-model="scheduledAt"
                                   class="form-control form-control-sm mb-2" required>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                Conferma programmazione
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if(isset($campaign) && $campaign->status === 'scheduled')
                <hr class="my-2">
                <div class="alert alert-info p-2 small mb-2">
                    🗓 Programmata per:<br>
                    <strong>{{ $campaign->scheduled_at->format('d/m/Y H:i') }}</strong>
                </div>
                <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="draft">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100"
                            onclick="return confirm('Annullare la programmazione e tornare a bozza?')">
                        Annulla programmazione
                    </button>
                </form>
            @endif

            @if(isset($campaign) && $campaign->isSending())
                <hr class="my-2">
                <form method="POST" action="{{ route('campaigns.pause', $campaign) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm w-100">
                        ⏸ Metti in pausa
                    </button>
                </form>
            @endif

            @if(isset($campaign) && $campaign->isPaused())
                <hr class="my-2">
                <div class="alert alert-warning p-2 small mb-2">⏸ Invio in pausa</div>
                <form method="POST" action="{{ route('campaigns.resume', $campaign) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        ▶ Riprendi invio
                    </button>
                </form>
            @endif

        {{-- Progress bar (visibile durante invio e pausa) --}}
        @if(isset($campaign) && in_array($campaign->status, ['sending', 'paused', 'sent']))
            <div class="mt-3 px-1" x-data="campaignProgress()" x-init="init()">
                <p class="small fw-semibold mb-1">Progresso invio</p>
                <div class="progress mb-1" style="height: 18px;">
                    <div class="progress-bar"
                         :class="status === 'sent' ? 'bg-success' : (status === 'paused' ? 'bg-warning' : 'bg-primary progress-bar-striped progress-bar-animated')"
                         :style="'width:' + percent + '%'"
                         x-text="percent + '%'">
                    </div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>✅ <span x-text="sent"></span> inviati</span>
                    <span x-show="failed > 0" class="text-danger">❌ <span x-text="failed"></span> falliti</span>
                    <span>📬 <span x-text="total"></span> totali</span>
                </div>
                <div x-show="status === 'sent'" class="mt-1 text-success small fw-semibold">Invio completato!</div>
            </div>
        @endif

    </div>

    {{-- Area editor --}}
    <div class="flex-grow-1 d-flex flex-column overflow-hidden">

        {{-- Tab bar --}}
        <div class="bg-white border-bottom px-3 d-flex align-items-center gap-1 py-2">
            <button type="button" class="btn btn-sm"
                    :class="tab === 'visual' ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="switchTab('visual')">
                Visual
            </button>
            <button type="button" class="btn btn-sm"
                    :class="tab === 'html' ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="switchTab('html')">
                HTML
            </button>
            <button type="button" class="btn btn-sm"
                    :class="tab === 'text' ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="tab = 'text'">
                Testo semplice
            </button>
            <button type="button" class="btn btn-sm"
                    :class="tab === 'preview' ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="switchTab('preview')">
                Anteprima
            </button>
            <button type="button" class="btn btn-sm"
                    :class="tab === 'immagini' ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="switchTab('immagini')">
                Immagini
            </button>
            <button type="button" class="btn btn-sm"
                    :class="tab === 'templates' ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="switchTab('templates')">
                Template
            </button>
            <span class="ms-auto text-muted small">Variabili: <code>@{{first_name}}</code> <code>@{{last_name}}</code> <code>@{{full_name}}</code> <code>@{{company}}</code> <code>@{{email}}</code> <code>@{{unsubscribe_url}}</code></span>
        </div>

        {{-- Tab: Unlayer visual editor --}}
        <div x-show="tab === 'visual'" class="flex-grow-1">
            <div id="unlayer-editor"></div>
        </div>

        {{-- Tab: HTML grezzo --}}
        <div x-show="tab === 'html'" class="flex-grow-1 p-3">
            <textarea class="form-control font-monospace h-100"
                      style="resize: none; font-size: 12px;"
                      x-model="htmlContent"
                      placeholder="Incolla o scrivi il codice HTML qui..."></textarea>
        </div>

        {{-- Tab: testo semplice --}}
        <div x-show="tab === 'text'" class="flex-grow-1 p-3">
            <textarea class="form-control h-100"
                      style="resize: none;"
                      x-model="textContent"
                      placeholder="Versione testo semplice dell'email (per client che non supportano HTML)..."></textarea>
        </div>

        {{-- Tab: anteprima --}}
        <div x-show="tab === 'preview'" x-cloak class="flex-grow-1 flex-column"
             :class="tab === 'preview' ? 'd-flex' : ''">
            <div class="bg-light border-bottom px-3 py-2 d-flex gap-2 align-items-center">
                <span class="small text-muted">Anteprima con dati di esempio</span>
                <div class="ms-auto d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            :class="previewDevice === 'desktop' ? 'active' : ''"
                            @click="previewDevice = 'desktop'">Desktop</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            :class="previewDevice === 'mobile' ? 'active' : ''"
                            @click="previewDevice = 'mobile'">Mobile</button>
                </div>
            </div>
            <div class="flex-grow-1 d-flex justify-content-center bg-light p-3 overflow-auto">
                <iframe id="previewFrame"
                        :style="previewDevice === 'mobile' ? 'width:375px;' : 'width:100%;'"
                        style="border: 1px solid #dee2e6; background: white; height: 100%;"
                        class="shadow-sm"></iframe>
            </div>
        </div>

        {{-- Tab: immagini --}}
        <div x-show="tab === 'immagini'" x-cloak class="flex-grow-1 flex-column overflow-hidden"
             :class="tab === 'immagini' ? 'd-flex' : ''">
            <div class="bg-light border-bottom px-3 py-2 d-flex align-items-center gap-2">
                <span class="small text-muted">Carica immagini e copia l'URL da incollare nei blocchi immagine di Unlayer.</span>
                <label class="btn btn-sm btn-primary ms-auto mb-0" style="cursor:pointer;">
                    + Carica immagine
                    <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none"
                           @change="uploadImage($event)">
                </label>
            </div>
            <div class="flex-grow-1 overflow-auto p-3">
                <div x-show="immaginiLoading" class="text-center text-muted py-4">Caricamento…</div>
                <div x-show="!immaginiLoading && immagini.length === 0" class="text-center text-muted py-4">
                    Nessuna immagine. Carica la prima immagine con il pulsante in alto.
                </div>
                <div class="row g-2" x-show="!immaginiLoading && immagini.length > 0">
                    <template x-for="img in immagini" :key="img.url">
                        <div class="col-6 col-xl-4">
                            <div class="card h-100">
                                <img :src="img.url" :alt="img.name"
                                     class="card-img-top"
                                     style="height: 100px; object-fit: cover;">
                                <div class="card-body p-2 d-flex flex-column gap-1">
                                    <span class="small text-truncate text-muted" x-text="img.name" style="font-size:11px;"></span>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1"
                                                @click="copyUrl(img.url)"
                                                x-text="copiedUrl === img.url ? '✓ Copiato!' : 'Copia URL'">
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                @click="deleteImage(img.name)">✕</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Tab: template --}}
        <div x-show="tab === 'templates'" x-cloak class="flex-grow-1 flex-column overflow-hidden"
             :class="tab === 'templates' ? 'd-flex' : ''">
            <div class="bg-light border-bottom px-3 py-2 d-flex align-items-center gap-2">
                <span class="small text-muted">Salva il design corrente come template riutilizzabile (header, footer, struttura base…)</span>
                <button type="button" class="btn btn-sm btn-primary ms-auto" @click="showSaveModal = true">
                    + Salva template corrente
                </button>
            </div>
            <div class="flex-grow-1 overflow-auto p-3">
                <div x-show="templatesLoading" class="text-center text-muted py-4">Caricamento…</div>
                <div x-show="!templatesLoading && templates.length === 0" class="text-center text-muted py-4">
                    Nessun template salvato. Crea il design di una email base e salvala come template.
                </div>
                <div class="row g-2" x-show="!templatesLoading && templates.length > 0">
                    <template x-for="t in templates" :key="t.id">
                        <div class="col-12">
                            <div class="card card-body py-2 px-3 d-flex flex-row align-items-center gap-2">
                                <span class="fw-semibold flex-grow-1 small" x-text="t.displayName"></span>
                                <span class="text-muted" style="font-size:11px" x-text="t.created_at"></span>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        @click="loadTemplate(t)">Carica</button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        @click="deleteTemplate(t.id)">✕</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>

    {{-- Modal salva template (dentro x-data per accedere a showSaveModal) --}}
    <div x-show="showSaveModal"
         class="position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
         style="background: rgba(0,0,0,.45); z-index: 9999;">
        <div class="bg-white rounded shadow p-4 mx-auto mt-5" style="width: 360px;" @click.stop>
            <h6 class="mb-3">Salva come template</h6>
            <input type="text" class="form-control mb-3" placeholder="Nome template (es. Header Newsletter)" x-model="templateName" @keydown.enter="saveTemplate()">
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="showSaveModal = false; templateName = ''">Annulla</button>
                <button type="button" class="btn btn-sm btn-primary" :disabled="!templateName.trim() || savingTemplate" @click="saveTemplate()">
                    <span x-show="!savingTemplate">Salva</span>
                    <span x-show="savingTemplate">Salvataggio…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://editor.unlayer.com/embed.js"></script>
<script>
const CSRF        = document.querySelector('meta[name=csrf-token]').content;
const BLOCKS_URL  = '{{ route('unlayer.blocks') }}';
const IMAGES_URL  = '{{ route('upload.images') }}';
const UPLOAD_URL  = IMAGES_URL;

function campaignEditor() {
    return {
        tab: 'visual',
        previewDevice: 'desktop',
        htmlContent: @json($campaign->html_content ?? ''),
        designJson:  @json($campaign->design_json ?? null),
        textContent: @json($campaign->text_content ?? ''),
        unlayerReady: false,
        templates: [],
        templatesLoading: false,
        showSaveModal: false,
        templateName: '',
        savingTemplate: false,
        immagini: [],
        immaginiLoading: false,
        copiedUrl: null,

        init() {
            unlayer.init({
                id: 'unlayer-editor',
                displayMode: 'email',
                locale: 'it-IT',
                appearance: { theme: 'light' },

                features: { userUploads: false },

                fonts: {
                    showDefaultFonts: true,
                    customFonts: [
                        { label: 'Inter',       value: "'Inter', sans-serif",      url: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' },
                        { label: 'Roboto',      value: "'Roboto', sans-serif",     url: 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap' },
                        { label: 'Open Sans',   value: "'Open Sans', sans-serif",  url: 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap' },
                        { label: 'Lato',        value: "'Lato', sans-serif",       url: 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap' },
                        { label: 'Montserrat',  value: "'Montserrat', sans-serif", url: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap' },
                        { label: 'Poppins',     value: "'Poppins', sans-serif",    url: 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap' },
                        { label: 'Raleway',     value: "'Raleway', sans-serif",    url: 'https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700&display=swap' },
                        { label: 'Nunito',      value: "'Nunito', sans-serif",     url: 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap' },
                        { label: 'Playfair',    value: "'Playfair Display', serif",url: 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap' },
                    ],
                },

            });

            unlayer.registerCallback('image', (file, done) => {
                const data = new FormData();
                data.append('file', file.attachments[0]);
                fetch(UPLOAD_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    body: data,
                })
                .then(r => r.json())
                .then(d => done({ progress: 100, url: d.url }))
                .catch(() => done({ progress: 0, url: '' }));
            });

            if (this.designJson) {
                unlayer.loadDesign(JSON.parse(this.designJson));
            }

            unlayer.addEventListener('design:updated', () => {
                unlayer.exportHtml((data) => {
                    this.htmlContent = data.html;
                });
            });

            this.unlayerReady = true;
        },

        switchTab(newTab) {
            this.tab = newTab;
            if (newTab === 'preview') {
                this.$nextTick(() => this.updatePreview());
            }
            if (newTab === 'templates') {
                this.loadTemplates();
            }
            if (newTab === 'immagini') {
                this.loadImmagini();
            }
        },

        updatePreview() {
            const frame = document.getElementById('previewFrame');
            const vars = {
                'first_name':      'Mario',
                'last_name':       'Rossi',
                'full_name':       'Mario Rossi',
                'company':         'Acme Srl',
                'email':           'mario.rossi@esempio.com',
                'unsubscribe_url': '#unsubscribe-preview',
            };
            let html = this.htmlContent;
            Object.entries(vars).forEach(([key, val]) => {
                html = html.split('{{' + key + '}}').join(val);
            });
            frame.srcdoc = html || '<p style="font-family:sans-serif;padding:20px;color:#999">Nessun contenuto HTML.</p>';
        },

        save() {
            if (this.unlayerReady) {
                unlayer.saveDesign((design) => {
                    this.designJson = JSON.stringify(design);
                    unlayer.exportHtml((data) => {
                        this.htmlContent = data.html;
                        this.$nextTick(() => document.getElementById('campaignForm').submit());
                    });
                });
            } else {
                document.getElementById('campaignForm').submit();
            }
        },

        loadTemplates() {
            this.templatesLoading = true;
            fetch(BLOCKS_URL)
                .then(r => r.json())
                .then(d => { this.templates = d.data; })
                .catch(() => { this.templates = []; })
                .finally(() => { this.templatesLoading = false; });
        },

        saveTemplate() {
            const name = this.templateName.trim();
            if (!name) return;

            const existing = this.templates.find(t => t.displayName === name);
            if (existing && !confirm('Un template con questo nome esiste già. Vuoi sostituirlo?')) return;

            this.savingTemplate = true;
            unlayer.saveDesign((design) => {
                const url    = existing ? BLOCKS_URL + '/' + existing.id : BLOCKS_URL;
                const method = existing ? 'PUT' : 'POST';
                fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ displayName: name, body: design }),
                })
                .then(r => r.json())
                .then(() => {
                    this.showSaveModal = false;
                    this.templateName = '';
                    this.loadTemplates();
                })
                .catch(() => alert('Errore nel salvataggio del template.'))
                .finally(() => { this.savingTemplate = false; });
            });
        },

        loadTemplate(t) {
            if (!confirm('Caricare il template "' + t.displayName + '"? Il design corrente verrà sostituito.')) return;
            unlayer.loadDesign(JSON.parse(JSON.stringify(t.body)));
            unlayer.exportHtml((data) => { this.htmlContent = data.html; });
            this.tab = 'visual';
        },

        deleteTemplate(id) {
            if (!confirm('Eliminare questo template?')) return;
            fetch(BLOCKS_URL + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF },
            })
            .then(() => { this.templates = this.templates.filter(t => t.id !== id); })
            .catch(() => alert('Errore nell\'eliminazione.'));
        },

        loadImmagini() {
            this.immaginiLoading = true;
            fetch(IMAGES_URL)
                .then(r => r.json())
                .then(d => { this.immagini = d.data; })
                .catch(() => { this.immagini = []; })
                .finally(() => { this.immaginiLoading = false; });
        },

        uploadImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';
            const data = new FormData();
            data.append('file', file);
            fetch(UPLOAD_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF },
                body: data,
            })
            .then(r => r.json())
            .then(img => { this.immagini.unshift(img); })
            .catch(() => alert('Errore nel caricamento dell\'immagine.'));
        },

        copyUrl(url) {
            const done = () => {
                this.copiedUrl = url;
                setTimeout(() => { this.copiedUrl = null; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done);
            } else {
                const el = document.createElement('textarea');
                el.value = url;
                el.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                done();
            }
        },

        deleteImage(name) {
            if (!confirm('Eliminare questa immagine?')) return;
            fetch(IMAGES_URL + '/' + encodeURIComponent(name), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF },
            })
            .then(() => { this.immagini = this.immagini.filter(i => i.name !== name); })
            .catch(() => alert('Errore nell\'eliminazione.'));
        }
    }
}

function campaignProgress() {
    return {
        status:  '{{ $campaign->status ?? 'draft' }}',
        total:   {{ $campaign->total_recipients ?? 0 }},
        sent:    0,
        failed:  0,
        pending: 0,
        percent: 0,
        running: false,

        init() {
            if (this.status === 'sending') {
                this.startLoop();
            }
        },

        async startLoop() {
            if (this.running) return;
            this.running = true;
            while (this.running) {
                const ok = await this.processBatch();
                if (!ok) break;
                // pausa breve tra un batch e l'altro
                await new Promise(r => setTimeout(r, 300));
            }
            this.running = false;
        },

        async processBatch() {
            try {
                const r = await fetch('{{ isset($campaign) ? route('campaigns.process-batch', $campaign) : '#' }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                const d = await r.json();
                this.status  = d.status;
                this.total   = d.total;
                this.sent    = d.sent;
                this.failed  = d.failed;
                this.pending = d.pending;
                this.percent = d.percent;
                return d.status === 'sending';
            } catch (e) {
                return false;
            }
        },

        pause() {
            this.running = false;
            fetch('{{ isset($campaign) ? route('campaigns.pause', $campaign) : '#' }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            }).then(() => { this.status = 'paused'; });
        },

        resume() {
            fetch('{{ isset($campaign) ? route('campaigns.resume', $campaign) : '#' }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            }).then(() => { this.status = 'sending'; this.startLoop(); });
        }
    }
}
</script>

</body>
</html>
