<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logos">
                <img src="{{ asset('images/cvsu-logo.png') }}" alt="Cavite State University logo">
                <img src="{{ asset('images/dcs-logo.png') }}" alt="Department of Computer Studies logo">
            </div>
            <div>
                <strong>Payroll System</strong>
                <small>DCS Attendance System</small>
            </div>
        </div>
        <nav>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            @if (auth()->user()->role === 'admin')
                <a class="{{ request()->routeIs('employees.*') ? 'active' : '' }}" href="{{ route('employees.index') }}">Faculty</a>
                <a class="{{ request()->routeIs('ranks.*') ? 'active' : '' }}" href="{{ route('ranks.index') }}">Faculty Ranks</a>
                <a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">User Accounts</a>
            @endif
            <a class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}" href="{{ route('attendance.index') }}">Attendance</a>
            <a class="{{ request()->routeIs('payroll.*') ? 'active' : '' }}" href="{{ route('payroll.index') }}">Payroll</a>
        </nav>
        <form class="logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Logout</button>
        </form>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1>@yield('title')</h1>
                <p>@yield('subtitle')</p>
            </div>
            <div class="topbar-meta">
                <span class="date-chip">{{ now()->format('M d, Y') }}</span>
                <div class="user-chip">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                </div>
            </div>
        </header>

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
