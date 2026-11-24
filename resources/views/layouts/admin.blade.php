<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Console') · Apple Worm Adventure</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                <span class="admin-brand-mark">🐛</span>
                <span><strong>APPLE WORM</strong><small>ADVENTURE CONTROL</small></span>
            </a>
            <div class="admin-side-label">WORKSPACE</div>
            <nav class="admin-nav" aria-label="Admin navigation">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span>▦</span> Overview</a>
                <a href="{{ route('admin.players') }}" class="{{ request()->routeIs('admin.players*') ? 'active' : '' }}"><span>♙</span> Players <em>Directory</em></a>
                <a href="{{ route('admin.levels') }}" class="{{ request()->routeIs('admin.levels*') ? 'active' : '' }}"><span>▧</span> Levels <em>25 maps</em></a>
                <a href="{{ route('admin.economy') }}" class="{{ request()->routeIs('admin.economy') ? 'active' : '' }}"><span>◈</span> Reward economy</a>
                <a href="{{ route('admin.achievements') }}" class="{{ request()->routeIs('admin.achievements*') ? 'active' : '' }}"><span>✦</span> Achievements <em>Rules</em></a>
            </nav>
            <div class="admin-side-label admin-side-label-bottom">GAME</div>
            <nav class="admin-nav" aria-label="Game reports">
                <a href="{{ route('admin.analytics') }}" class="{{ request()->routeIs('admin.analytics') ? 'active' : '' }}"><span>⌁</span> Analytics <em>Reports</em></a>
            </nav>
            <div class="admin-side-label admin-side-label-bottom">SYSTEM</div>
            <nav class="admin-nav" aria-label="System administration">
                <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}"><span>⚙</span> Settings</a>
                <a href="{{ route('admin.audit') }}" class="{{ request()->routeIs('admin.audit*') ? 'active' : '' }}"><span>≡</span> Audit logs <em>History</em></a>
            </nav>
            <div class="admin-sidebar-bottom">
                <div class="admin-user-chip"><span class="admin-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>Administrator</small></span></div>
                <a class="admin-back-link" href="{{ route('dashboard') }}">↗ Return to player app</a>
            </div>
        </aside>

        <div class="admin-main-wrap">
            <header class="admin-topbar">
                <button class="admin-menu-toggle" id="adminMenuToggle" type="button" aria-label="Toggle admin navigation" aria-expanded="false">☰</button>
                <div class="admin-breadcrumb">CONTROL PANEL <span>/</span> @yield('section', 'OVERVIEW')</div>
                <div class="admin-top-actions"><span class="admin-live"><i></i> System workspace</span><span class="admin-top-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span></div>
            </header>

            <main class="admin-content">
                @if(session('status'))<div class="admin-flash success">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="admin-flash error">{{ $errors->first() }}</div>@endif
                @yield('content')
            </main>
            <footer class="admin-footer"><span>APPLE WORM ADVENTURE</span><span>Admin workspace · Access restricted</span></footer>
        </div>
    </div>
    <div class="admin-sidebar-scrim" id="adminSidebarScrim"></div>
    <script>
        (() => {
            const button = document.getElementById('adminMenuToggle');
            const sidebar = document.getElementById('adminSidebar');
            const scrim = document.getElementById('adminSidebarScrim');
            if (!button || !sidebar || !scrim) return;
            const close = () => { sidebar.classList.remove('is-open'); scrim.classList.remove('is-visible'); button.setAttribute('aria-expanded', 'false'); };
            button.addEventListener('click', () => {
                const opening = !sidebar.classList.contains('is-open');
                sidebar.classList.toggle('is-open', opening);
                scrim.classList.toggle('is-visible', opening);
                button.setAttribute('aria-expanded', String(opening));
            });
            scrim.addEventListener('click', close);
            document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
