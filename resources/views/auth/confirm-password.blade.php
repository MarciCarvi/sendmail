<x-guest-layout>
    <p class="text-muted small mb-3">Conferma la tua password per continuare.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   required autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Conferma</button>
        </div>
    </form>
</x-guest-layout>
