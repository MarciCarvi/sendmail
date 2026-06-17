<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenza scaduta — SendMail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .blocked-card { max-width: 520px; margin: 100px auto; }
    </style>
</head>
<body>
    <div class="blocked-card card shadow-sm">
        <div class="card-body p-5 text-center">
            <div class="mb-4" style="font-size:3rem">🔒</div>
            <h4 class="fw-bold mb-2">Licenza non valida</h4>
            <p class="text-muted mb-4">
                Il periodo di grazia è scaduto. Per continuare a usare SendMail
                è necessario inserire una licenza valida.
            </p>
            @if(!empty($status['error']))
                <div class="alert alert-danger text-start small">{{ $status['error'] }}</div>
            @endif
            <a href="{{ route('settings.index') }}" class="btn btn-primary">
                Vai alle impostazioni
            </a>
        </div>
    </div>
</body>
</html>
