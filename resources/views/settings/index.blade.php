<x-app-layout>
    <x-slot name="title">Impostazioni</x-slot>

    <div class="row">
        <div class="col-lg-8">

            <form method="POST" action="{{ route('settings.update') }}">
                @csrf
                @method('PUT')

                {{-- App --}}
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Applicazione</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nome applicazione</label>
                            <input type="text" name="app_name" class="form-control @error('app_name') is-invalid @enderror"
                                   value="{{ old('app_name', $settings['app_name']) }}">
                            @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome mittente default</label>
                            <input type="text" name="default_from_name" class="form-control @error('default_from_name') is-invalid @enderror"
                                   value="{{ old('default_from_name', $settings['default_from_name']) }}">
                            @error('default_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email mittente default</label>
                            <input type="email" name="default_from_email" class="form-control @error('default_from_email') is-invalid @enderror"
                                   value="{{ old('default_from_email', $settings['default_from_email']) }}">
                            @error('default_from_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- SES --}}
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Amazon SES</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Access Key ID</label>
                            <input type="text" name="ses_key" class="form-control @error('ses_key') is-invalid @enderror"
                                   placeholder="{{ $settings['ses_key'] ? '••••••••••••••••' : 'Inserisci chiave' }}"
                                   value="{{ old('ses_key') }}" autocomplete="off">
                            @error('ses_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($settings['ses_key'])
                                <div class="form-text">Lascia vuoto per mantenere la chiave esistente.</div>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Secret Access Key</label>
                            <input type="password" name="ses_secret" class="form-control @error('ses_secret') is-invalid @enderror"
                                   placeholder="{{ $settings['ses_secret'] ? '••••••••••••••••' : 'Inserisci secret' }}"
                                   value="{{ old('ses_secret') }}" autocomplete="off">
                            @error('ses_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($settings['ses_secret'])
                                <div class="form-text">Lascia vuoto per mantenere il secret esistente.</div>
                            @endif
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Regione AWS</label>
                                <select name="ses_region" class="form-select @error('ses_region') is-invalid @enderror">
                                    @foreach(['eu-west-1','eu-west-2','eu-west-3','eu-central-1','us-east-1','us-east-2','us-west-2','ap-southeast-1','ap-southeast-2'] as $region)
                                        <option value="{{ $region }}" {{ old('ses_region', $settings['ses_region']) === $region ? 'selected' : '' }}>
                                            {{ $region }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('ses_region')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rate invio (email/sec)</label>
                                <input type="number" name="ses_sending_rate" min="1" max="200"
                                       class="form-control @error('ses_sending_rate') is-invalid @enderror"
                                       value="{{ old('ses_sending_rate', $settings['ses_sending_rate']) }}">
                                @error('ses_sending_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">SES sandbox: 1/sec. Produzione: vedi quota SES.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Configuration Set <span class="text-muted fw-normal">(opzionale)</span></label>
                            <input type="text" name="ses_configuration_set"
                                   class="form-control @error('ses_configuration_set') is-invalid @enderror"
                                   value="{{ old('ses_configuration_set', $settings['ses_configuration_set'] ?? '') }}"
                                   placeholder="es. sendmail-notifications">
                            @error('ses_configuration_set')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Necessario per tracciare le delivery. Crea un Configuration Set in AWS SES con evento Delivery → SNS → <code>{{ config('app.url') }}/webhook/ses</code></div>
                        </div>

                        {{-- Test connessione --}}
                        <div x-data="{ loading: false, result: null, ok: null }" class="mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    @click="
                                        loading = true; result = null;
                                        fetch('{{ route('settings.test-ses') }}', {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                        })
                                        .then(r => r.json())
                                        .then(d => { result = d.message; ok = d.success; loading = false; })
                                        .catch(() => { result = 'Errore di rete'; ok = false; loading = false; })
                                    ">
                                <span x-show="!loading">Testa connessione SES</span>
                                <span x-show="loading">Verifica in corso...</span>
                            </button>
                            <span x-show="result !== null" class="ms-3 small"
                                  :class="ok ? 'text-success' : 'text-danger'" x-text="result"></span>
                        </div>
                    </div>
                </div>

                {{-- Domini bloccati --}}
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Domini bloccati</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Un dominio per riga. Le email con questi domini non potranno essere aggiunte ad alcuna lista (né manualmente, né via import, né via form pubblico).
                        </p>
                        <textarea name="blocked_domains" class="form-control font-monospace" rows="6"
                                  placeholder="spam-domain.com&#10;another-spam.net">{{ old('blocked_domains', $settings['blocked_domains']) }}</textarea>
                    </div>
                </div>

                {{-- Aggiornamenti --}}
                <div class="card mb-4">
                    <div class="card-header fw-semibold d-flex align-items-center">
                        Aggiornamenti
                        <span class="badge text-bg-secondary ms-auto">v{{ config('sendmail.version') }}</span>
                    </div>
                    <div class="card-body">
                        <div x-data="{ loading: false, result: null, hasUpdate: false, version: null }" class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading"
                                    @click="
                                        loading = true; result = null;
                                        fetch('{{ route('update.force-check') }}', {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                        })
                                        .then(r => r.json())
                                        .then(d => {
                                            loading = false;
                                            hasUpdate = d.has_update;
                                            version = d.latest_version;
                                            result = d.has_update
                                                ? 'Disponibile v' + (d.latestVersion || d.latest_version)
                                                : 'Sei già all\'ultima versione (' + (d.currentVersion || d.current_version) + ')';
                                        })
                                        .catch(() => { loading = false; result = 'Errore di rete'; })
                                    ">
                                <span x-show="!loading">Verifica aggiornamenti</span>
                                <span x-show="loading">Verifica in corso...</span>
                            </button>
                            <span x-show="result !== null" class="small"
                                  :class="hasUpdate ? 'text-primary fw-semibold' : 'text-success'"
                                  x-text="result"></span>
                            <a x-show="hasUpdate" href="{{ route('dashboard') }}"
                               class="btn btn-primary btn-sm">Vai alla dashboard per aggiornare</a>
                        </div>
                    </div>
                </div>

                {{-- Licenza --}}
                <div class="card mb-4">
                    <div class="card-header fw-semibold d-flex align-items-center gap-2">
                        Licenza
                        @if($licenseStatus['status'] === 'valid')
                            <span class="badge text-bg-success ms-auto">Attiva</span>
                        @elseif($licenseStatus['status'] === 'grace')
                            <span class="badge text-bg-warning ms-auto">Grazia — {{ $licenseStatus['days_left'] }} {{ $licenseStatus['days_left'] === 1 ? 'giorno' : 'giorni' }} rimanenti</span>
                        @else
                            <span class="badge text-bg-danger ms-auto">Non valida</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($licenseStatus['status'] !== 'valid' && $licenseStatus['error'])
                            <div class="alert alert-warning small py-2 mb-3">{{ $licenseStatus['error'] }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label">Chiave di licenza</label>
                            <div x-data="{ show: false }" class="input-group">
                                <input :type="show ? 'text' : 'password'" name="license_key"
                                       class="form-control font-monospace @error('license_key') is-invalid @enderror"
                                       value="{{ old('license_key', $settings['license_key'] ?? '') }}"
                                       placeholder="Inserisci il codice licenza ricevuto" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" @click="show = !show"
                                        :title="show ? 'Nascondi' : 'Mostra'">
                                    <svg x-show="!show" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="show" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            @error('license_key')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">
                                La licenza viene verificata tramite il dominio configurato in <code>APP_URL</code> (<strong>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</strong>).
                                Al primo utilizzo il dominio viene associato automaticamente.
                            </div>
                        </div>

                        <div x-data="{ loading: false, result: null, ok: null }" class="mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    @click="
                                        loading = true; result = null;
                                        fetch('{{ route('license.check') }}', {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                        })
                                        .then(r => r.json())
                                        .then(d => { result = d.status === 'valid' ? 'Licenza valida' : (d.error || 'Stato: ' + d.status); ok = d.status === 'valid'; loading = false; })
                                        .catch(() => { result = 'Errore di rete'; ok = false; loading = false; })
                                    ">
                                <span x-show="!loading">Verifica licenza</span>
                                <span x-show="loading">Verifica in corso...</span>
                            </button>
                            <span x-show="result !== null" class="ms-3 small"
                                  :class="ok ? 'text-success' : 'text-danger'" x-text="result"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Salva impostazioni</button>
            </form>

        </div>
    </div>
</x-app-layout>
