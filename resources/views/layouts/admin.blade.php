<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · TradeSmart Quiz Arena</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('head')
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar" id="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="brand">
            <img src="{{ asset('images/logo-mark.png') }}" alt="">
            <span><strong>TradeSmart</strong><small>Operations Quiz Arena</small></span>
        </a>
        @php $r = request()->route()?->getName() ?? ''; @endphp
        <nav class="nav flex-column">
            <a class="nav-link {{ $r === 'admin.dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Overview</a>
            <a class="nav-link {{ str_starts_with($r, 'admin.sessions') ? 'active' : '' }}" href="{{ route('admin.sessions.index') }}"><i class="bi bi-controller"></i> Quiz Sessions</a>
            <a class="nav-link {{ str_starts_with($r, 'admin.questions') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}"><i class="bi bi-patch-question"></i> Questions</a>
            <a class="nav-link {{ str_starts_with($r, 'admin.categories') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i> Categories</a>
            <a class="nav-link {{ str_starts_with($r, 'admin.participants') ? 'active' : '' }}" href="{{ route('admin.participants.index') }}"><i class="bi bi-people"></i> Team Members</a>
            <a class="nav-link {{ str_starts_with($r, 'admin.reports') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="bi bi-graph-up-arrow"></i> Reports</a>
            <a class="nav-link {{ str_starts_with($r, 'admin.reactions') ? 'active' : '' }}" href="{{ route('admin.reactions.index') }}"><i class="bi bi-emoji-laughing"></i> Reactions</a>
            @can('admin')
                <div class="nav-section">Administration</div>
                <a class="nav-link {{ str_starts_with($r, 'admin.settings') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}"><i class="bi bi-sliders"></i> Defaults &amp; Sounds</a>
                <a class="nav-link {{ str_starts_with($r, 'admin.users') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-person-badge"></i> Users</a>
            @endcan
        </nav>
        <div class="sidebar-foot">
            <div class="small text-white-50 mb-2"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }} <span class="badge bg-secondary">{{ auth()->user()->role }}</span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right"></i> Log out</button>
            </form>
        </div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-sm btn-light d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="bi bi-list"></i></button>
            <h1 class="h4 mb-0">@yield('title', 'Dashboard')</h1>
            <div class="ms-auto d-flex gap-2">@yield('actions')</div>
        </header>
        <div class="admin-content">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
    // Confirm destructive actions (forms with data-confirm)
    document.addEventListener('submit', e => {
        const msg = e.target.dataset.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });
</script>
@stack('scripts')
</body>
</html>
