<x-guest-layout>
    <p class="text-muted small mb-3">Verifica il tuo indirizzo email cliccando sul link che ti abbiamo inviato.</p>

    @if(session('status') === 'verification-link-sent')
        <div class="alert alert-success mb-3">Un nuovo link di verifica è stato inviato alla tua email.</div>
    @endif

    <div class="d-flex align-items-center justify-content-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Invia di nuovo</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-muted">Logout</button>
        </form>
    </div>
</x-guest-layout>
