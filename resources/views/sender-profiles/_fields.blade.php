@php $p = $profile ?? null; @endphp
<div class="mb-3">
    <label class="form-label fw-semibold small">Nome profilo</label>
    <input type="text" name="name" class="form-control" required maxlength="100"
           value="{{ old('name', $p->name ?? '') }}" placeholder="es. Cliente Rossi">
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Nome mittente</label>
        <input type="text" name="from_name" class="form-control" required maxlength="100"
               value="{{ old('from_name', $p->from_name ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Email mittente</label>
        <input type="email" name="from_email" class="form-control" required
               value="{{ old('from_email', $p->from_email ?? '') }}">
        <div class="form-text">Deve essere verificata in SES.</div>
    </div>
</div>
<div class="mb-3">
    <label class="form-label fw-semibold small">Reply-to <span class="text-muted">(opzionale)</span></label>
    <input type="email" name="reply_to" class="form-control" value="{{ old('reply_to', $p->reply_to ?? '') }}">
</div>
<div class="mb-1">
    <label class="form-label fw-semibold small">Configuration Set SES <span class="text-muted">(opzionale)</span></label>
    <input type="text" name="configuration_set" class="form-control" maxlength="100"
           value="{{ old('configuration_set', $p->configuration_set ?? '') }}">
    <div class="form-text">Nome esatto del set in SES. Se vuoto si usa quello globale di Impostazioni.</div>
</div>
