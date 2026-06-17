<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iscriviti — {{ $list->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .form-card { max-width: 480px; margin: 2rem auto; }
    </style>
</head>
<body>
<div class="form-card px-3">
    <div class="card shadow-sm">
        <div class="card-body p-4">

            @if(isset($outcome) && $outcome === 'success')
                <div class="text-center py-3">
                    <div style="font-size:3rem">✅</div>
                    <h5 class="mt-2">{{ $outcomeMessage }}</h5>
                </div>

            @elseif(isset($outcome) && $outcome === 'confirm')
                <div class="text-center py-3">
                    <div style="font-size:3rem">📧</div>
                    <h5 class="mt-2">{{ $outcomeMessage }}</h5>
                </div>

            @elseif(isset($outcome) && $outcome === 'error')
                <div class="alert alert-danger">{{ $outcomeMessage }}</div>
                @include('public._subscribe_form', ['list' => $list])

            @else
                @include('public._subscribe_form', ['list' => $list])
            @endif

        </div>
    </div>
</div>
</body>
</html>
