<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Apple Worm Adventure') · AWA</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
    <header class="topbar" id="siteHeader">
        <a class="brand" href="{{ route('home') }}" aria-label="Apple Worm Adventure home">
            <span class="brand-mark" aria-hidden="true">🐛</span>
            <span class="brand-name">APPLE WORM <b>ADVENTURE</b></span>
        </a>

        <button class="nav-toggle" id="navToggle" type="button" aria-label="Open navigation menu"
            aria-controls="primaryNav" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="primary-nav" id="primaryNav" aria-label="Main navigation">
            <a href="{{ route('home') }}">Home</a>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('levels') }}">Levels</a>
                <a href="{{ route('wallet') }}">Wallet</a>
                <a href="{{ route('leaderboard') }}">Leaderboard</a>
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button class="nav-button" type="submit">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}">Login</a>
                <a class="nav-cta" href="{{ route('register') }}">Play now</a>
            @endauth
        </nav>
    </header>
    <main class="page-wrap">
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
    <footer class="footer">An original puzzle-game prototype · Built with Laravel + JavaScript · Rewarded ads are simulated in development</footer>
    <script>
        (() => {
            const toggle = document.getElementById('navToggle');
            const nav = document.getElementById('primaryNav');
            if (!toggle || !nav) return;

            const closeMenu = () => {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open navigation menu');
                nav.classList.remove('is-open');
                document.body.classList.remove('nav-open');
            };

            toggle.addEventListener('click', () => {
                const opening = toggle.getAttribute('aria-expanded') !== 'true';
                toggle.setAttribute('aria-expanded', String(opening));
                toggle.setAttribute('aria-label', opening ? 'Close navigation menu' : 'Open navigation menu');
                nav.classList.toggle('is-open', opening);
                document.body.classList.toggle('nav-open', opening);
            });

            nav.querySelectorAll('a, button').forEach((item) => item.addEventListener('click', closeMenu));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeMenu();
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('#siteHeader')) closeMenu();
            });
            window.addEventListener('resize', () => {
                if (window.innerWidth > 760) closeMenu();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
