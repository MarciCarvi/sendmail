<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    {{-- License warning banner --}}
    @if($licenseStatus['status'] === 'grace')
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4 py-2">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div class="flex-grow-1 small">
            <strong>Licenza non valida</strong> — {{ $licenseStatus['error'] }}
            Il periodo di grazia scade tra <strong>{{ $licenseStatus['days_left'] }} {{ $licenseStatus['days_left'] === 1 ? 'giorno' : 'giorni' }}</strong>.
        </div>
        <a href="{{ route('settings.index') }}#licenza" class="btn btn-warning btn-sm">Configura licenza</a>
    </div>
    @endif

    {{-- Update available banner --}}
    @if($updateInfo['has_update'])
    <div x-data="updater()" class="alert alert-primary d-flex align-items-center gap-3 mb-4 py-2">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        <div class="flex-grow-1 small">
            <strong>Aggiornamento disponibile</strong> — versione <strong>{{ $updateInfo['latest_version'] }}</strong>
            (attuale: {{ $updateInfo['current_version'] }})
        </div>
        <button @click="applyUpdate('{{ $updateInfo['latest_version'] }}')" :disabled="applying"
                class="btn btn-primary btn-sm" x-text="applying ? 'Aggiornamento in corso...' : 'Aggiorna ora'"></button>
    </div>
    @endif

    {{-- Post-update changelog (shown once after a fresh update) --}}
    @if(session('show_changelog'))
    <div x-data="changelogViewer()" x-init="loadChangelog()" class="mb-4">
        <div x-show="showModal" x-cloak
             class="modal fade show d-block" style="background:rgba(0,0,0,.5)" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Novità di SendMail {{ session('updated_version') }}</h5>
                        <button type="button" class="btn-close" @click="close()"></button>
                    </div>
                    <div class="modal-body" style="max-height:60vh;overflow-y:auto">
                        <div x-html="changelogHtml"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" @click="close()">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#EBE4FD">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#8B5CF6" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1 mb-1">{{ number_format($lists) }}</div>
                        <div class="small text-muted">Liste attive</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#E4F4EC">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#1FA971" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1 mb-1">{{ number_format($subscribers) }}</div>
                        <div class="small text-muted">
                            Iscritti attivi
                            @if($newSubscribers > 0)
                                <span class="badge text-bg-success ms-1">+{{ $newSubscribers }} questo mese</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#E9F1FD">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#2D74E0" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1 mb-1">{{ number_format($campaigns) }}</div>
                        <div class="small text-muted">Campagne inviate</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#FBF0DC">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#E0962A" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1 mb-1">{{ number_format($sent24h) }}</div>
                        <div class="small text-muted">Email inviate (24h)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Open rate medio + campagne recenti --}}
    <div class="row g-3">

        {{-- Open rate medio --}}
        @if($avgOpenRate !== null)
        <div class="col-xl-3 col-md-4">
            <div class="card h-100">
                <div class="card-body text-center py-4">
                    <div class="fs-1 fw-bold" style="color:#8B5CF6">{{ $avgOpenRate }}%</div>
                    <div class="text-muted small mt-1">Open rate medio<br><span class="text-muted" style="font-size:.75rem">ultime {{ $recentCampaigns->count() }} campagne</span></div>
                </div>
            </div>
        </div>
        @endif

        {{-- Campagne recenti --}}
        <div class="{{ $avgOpenRate !== null ? 'col-xl-9 col-md-8' : 'col-12' }}">
            <div class="card">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Campagne recenti</span>
                    <a href="{{ route('campaigns.index') }}" class="btn btn-sm btn-outline-primary">Tutte le campagne</a>
                </div>
                @if($recentCampaigns->isEmpty())
                    <div class="card-body text-center text-muted py-5">
                        <p class="mb-2">Nessuna campagna inviata ancora.</p>
                        <a href="{{ route('campaigns.create') }}" class="btn btn-primary btn-sm">Crea la prima campagna</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Oggetto</th>
                                    <th>Inviata</th>
                                    <th class="text-end">Destinatari</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentCampaigns as $campaign)
                                <tr>
                                    <td class="fw-medium">{{ $campaign->subject }}</td>
                                    <td class="text-muted small">
                                        {{ $campaign->sent_at?->diffForHumans() }}
                                    </td>
                                    <td class="text-end">{{ number_format($campaign->total_recipients) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('reports.show', $campaign) }}" class="btn btn-sm btn-outline-secondary">Report</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>

    @if($lists === 0 && $subscribers === 0 && $campaigns === 0)
    <div class="alert alert-primary mt-4 d-flex align-items-center gap-3">
        <span style="font-size:1.5rem">👋</span>
        <div>
            <strong>Benvenuto in SendMail!</strong>
            Inizia creando la tua prima lista, poi importa gli iscritti e crea una campagna.
            <div class="mt-2 d-flex gap-2">
                <a href="{{ route('lists.index') }}" class="btn btn-primary btn-sm">Crea una lista</a>
                <a href="{{ route('settings.index') }}" class="btn btn-outline-primary btn-sm">Configura SES</a>
            </div>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
    const CSRF_TOKEN       = document.querySelector('meta[name=csrf-token]').content;
    const UPDATE_APPLY_URL = '{{ route('update.apply') }}';
    const CHANGELOG_URL    = '{{ route('update.changelog') }}';

    function updater() {
        return {
            applying: false,

            applyUpdate(version) {
                if (!confirm('Applicare l\'aggiornamento alla versione ' + version + '?\nL\'operazione richiede circa 30-60 secondi.')) return;
                this.applying = true;

                fetch(UPDATE_APPLY_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ version }),
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        window.location.reload();
                    } else {
                        alert('Aggiornamento fallito: ' + (d.error || 'Errore sconosciuto'));
                        this.applying = false;
                    }
                })
                .catch(() => {
                    alert('Errore di rete durante l\'aggiornamento.');
                    this.applying = false;
                });
            },
        };
    }

    function changelogViewer() {
        return {
            showModal: false,
            changelogHtml: '',

            loadChangelog() {
                fetch(CHANGELOG_URL)
                    .then(r => r.json())
                    .then(d => {
                        this.changelogHtml = this.markdownToHtml(d.content || '');
                        this.showModal = true;
                    });
            },

            close() {
                this.showModal = false;
            },

            markdownToHtml(md) {
                return md
                    .replace(/^## (.+)$/gm, '<h5 class="mt-4 mb-2">$1</h5>')
                    .replace(/^### (.+)$/gm, '<h6 class="mt-3 mb-1">$1</h6>')
                    .replace(/^- (.+)$/gm, '<li>$1</li>')
                    .replace(/(<li>.*<\/li>\n?)+/g, s => '<ul class="mb-2">' + s + '</ul>')
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\n\n/g, '<br>');
            },
        };
    }
    </script>
    @endpush

</x-app-layout>
