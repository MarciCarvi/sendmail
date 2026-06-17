<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'SendMail') }}</title>

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <meta name="theme-color" content="#8B5CF6">

    {{-- Font brand --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>

<body>

    <div class="d-flex" style="min-height: 100vh;">

        {{-- Sidebar --}}
        <nav class="sm-sidebar d-flex flex-column flex-shrink-0 p-3">
            <a href="{{ route('dashboard') }}" class="sm-sidebar-brand d-flex align-items-center gap-2 mb-4 py-1">
                <img src="{{ asset('img/sendmail-lockup-horizontal-white.svg') }}" alt="" width="200">
            </a>

            <ul class="nav nav-pills flex-column mb-auto gap-1">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                        class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('lists.index') }}"
                        class="nav-link {{ request()->routeIs('lists.*') ? 'active' : '' }}">
                        Liste
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('campaigns.index') }}"
                        class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}">
                        Campagne
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('reports.index') }}"
                        class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        Report
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('blacklist.index') }}"
                        class="nav-link {{ request()->routeIs('blacklist.*') ? 'active' : '' }}">
                        Blacklist
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('settings.index') }}"
                        class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        Impostazioni
                    </a>
                </li>
            </ul>

            <hr class="border-secondary opacity-25">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                    data-bs-toggle="dropdown" style="opacity:.8;font-size:.875rem">
                    {{ Auth::user()->name }}
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        {{-- Main content --}}
        <div class="flex-grow-1 d-flex flex-column" style="min-width: 0;">
            {{-- Top bar --}}
            <header class="sm-topbar px-4 py-3 d-flex align-items-center justify-content-between">
                <h1 class="h5 mb-0 fw-semibold">{{ $title ?? '' }}</h1>
                <div>{{ $actions ?? '' }}</div>
            </header>

            {{-- Flash messages --}}
            <div class="px-4 pt-3">
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif
                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif
            </div>

            <main class="flex-grow-1 p-4">
                {{ $slot }}
            </main>
        </div>

    </div>

    @stack('scripts')
</body>

</html>