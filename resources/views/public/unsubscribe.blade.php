<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Disiscrizione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="card shadow-sm" style="max-width: 480px; width: 100%;">
    <div class="card-body text-center p-5">
        <h4 class="mb-3">Conferma disiscrizione</h4>
        <p class="text-muted mb-4">
            Stai per disiscriverti con l'indirizzo <strong>{{ $subscriber->email }}</strong>.<br>
            Non riceverai più email da noi.
        </p>
        <form method="POST" action="{{ route('unsubscribe.confirm', $token) }}">
            @csrf
            <button type="submit" class="btn btn-danger w-100 mb-2">Confermo, disiscrivimi</button>
            <a href="/" class="btn btn-outline-secondary w-100">Annulla</a>
        </form>
    </div>
</div>
</body>
</html>
