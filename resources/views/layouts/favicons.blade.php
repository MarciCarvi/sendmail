{{-- Favicon (?v=version: forza i browser a ri-scaricarla a ogni release) --}}
@php($faviconV = config('sendmail.version'))
<link rel="icon" href="{{ asset('favicon/favicon.ico') }}?v={{ $faviconV }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32.png') }}?v={{ $faviconV }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16.png') }}?v={{ $faviconV }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}?v={{ $faviconV }}">
<link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
