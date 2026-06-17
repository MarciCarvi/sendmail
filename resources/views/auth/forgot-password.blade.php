<x-guest-layout>
    <p class="text-muted small mb-3">Inserisci la tua email e ti invieremo un link per reimpostare la password.</p>

    @if(session('status'))
        <div class="alert alert-success mb-3">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Invia link di reset</button>
        </div>
    </form>
</x-guest-layout>
