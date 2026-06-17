<div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', $subscriber?->email) }}" required>
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row">
    <div class="col mb-3">
        <label class="form-label">Nome</label>
        <input type="text" name="first_name" class="form-control"
               value="{{ old('first_name', $subscriber?->first_name) }}">
    </div>
    <div class="col mb-3">
        <label class="form-label">Cognome</label>
        <input type="text" name="last_name" class="form-control"
               value="{{ old('last_name', $subscriber?->last_name) }}">
    </div>
</div>
<div class="mb-3">
    <label class="form-label">Azienda</label>
    <input type="text" name="company" class="form-control"
           value="{{ old('company', $subscriber?->company) }}">
</div>
