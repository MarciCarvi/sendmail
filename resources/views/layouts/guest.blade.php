<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SendMail') }}</title>

    <link rel="icon" href="{{ asset('favicon/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#8B5CF6">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body>
    <div class="min-vh-100 d-flex align-items-center justify-content-center">
        <div class="w-100" style="max-width: 420px;">
            <div class="text-center mb-4">
                <a href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                    <img src="{{ asset('img/sendmail-lockup-horizontal.svg') }}" alt="SendMail" height="64">
                </a>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</body>

</html>