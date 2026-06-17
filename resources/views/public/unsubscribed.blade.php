<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Disiscrizione confermata</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="card shadow-sm" style="max-width: 480px; width: 100%;">
    <div class="card-body text-center p-5">
        <div class="mb-3" style="font-size: 3rem;">✅</div>
        <h4 class="mb-3">Disiscrizione confermata</h4>
        <p class="text-muted">
            L'indirizzo <strong>{{ $subscriber->email }}</strong> è stato rimosso dalla lista.<br>
            Non riceverai più email da noi.
        </p>
    </div>
</div>
</body>
</html>
