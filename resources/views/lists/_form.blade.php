<div class="mb-3">
    <label class="form-label">Nome lista</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $list?->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label">Nome mittente</label>
    <input type="text" name="from_name" class="form-control @error('from_name') is-invalid @enderror"
           value="{{ old('from_name', $list?->from_name) }}" required>
    @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label">Email mittente</label>
    <input type="email" name="from_email" class="form-control @error('from_email') is-invalid @enderror"
           value="{{ old('from_email', $list?->from_email) }}" required>
    @error('from_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label">Reply-to <span class="text-muted">(opzionale)</span></label>
    <input type="email" name="reply_to" class="form-control @error('reply_to') is-invalid @enderror"
           value="{{ old('reply_to', $list?->reply_to) }}">
    @error('reply_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="form-check">
    <input type="hidden" name="double_optin" value="0">
    <input type="checkbox" name="double_optin" value="1" class="form-check-input" id="double_optin"
           {{ old('double_optin', $list?->double_optin) ? 'checked' : '' }}>
    <label class="form-check-label" for="double_optin">Double opt-in</label>
</div>
