<h5 class="mb-3">Iscriviti a <strong>{{ $list->name }}</strong></h5>

<form method="POST" action="{{ route('subscribe.submit', $list->api_token) }}">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-semibold">Email *</label>
        <input type="email" name="email" class="form-control" required
               value="{{ old('email') }}" placeholder="tua@email.com">
        @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label small fw-semibold">Nome</label>
            <input type="text" name="first_name" class="form-control"
                   value="{{ old('first_name') }}" placeholder="Mario">
        </div>
        <div class="col-6">
            <label class="form-label small fw-semibold">Cognome</label>
            <input type="text" name="last_name" class="form-control"
                   value="{{ old('last_name') }}" placeholder="Rossi">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold">Azienda</label>
        <input type="text" name="company" class="form-control"
               value="{{ old('company') }}" placeholder="Acme Srl">
    </div>
    <button type="submit" class="btn btn-primary w-100">Iscriviti</button>
</form>
