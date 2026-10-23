<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Transmittals') · ORDO</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --sidebar-width: 230px;
            --sidebar-collapsed-width: 78px;
            --sidebar-bg: #ffffff;
            --sidebar-border: #e2e8f0;
            --icon-bg: #f1f3f7;
            --icon-border: #e5e7eb;
            --icon-color: #000000;
            --text-color: #111827;
            --muted-color: #64748b;
            --active-bg: #eff6ff;
            --active-border: #dbeafe;
            --active-color: #2563eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--text-color);
            background: #f8fafc;
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        /* Main application layout */
        .shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            width: var(--sidebar-width);
            height: 100vh;
            padding: 16px 10px 12px;
            overflow-x: hidden;
            overflow-y: auto;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            transition: width 0.2s ease, padding 0.2s ease;
        }

        .main {
            flex: 1;
            min-width: 0;
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.2s ease;
        }

        /* Brand and sidebar header */
        .brand-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 43px;
            padding: 0 5px 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            color: #000000;
        }

        .brand-mark {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            background: #f1f3f7;
            color: #000000;
            font-size: 12px;
            font-weight: 800;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            gap: 3px;
        }

        .brand-text strong {
            overflow: hidden;
            color: #000000;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.3;
            text-overflow: ellipsis;
        }

        .brand-text small {
            color: #64748b;
            font-size: 9px;
            letter-spacing: 0.35px;
        }

        .sidebar-toggle {
            display: grid;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            padding: 0;
            place-items: center;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #000000;
            cursor: pointer;
        }

        .sidebar-toggle:hover {
            background: #f1f3f7;
        }

        .nav-heading {
            padding: 17px 10px 10px;
        }

        .nav-heading strong {
            display: block;
            color: #000000;
            font-size: 12px;
            font-weight: 700;
        }

        .nav-heading small {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 10px;
        }

        /* Navigation links */
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            min-height: 42px;
            padding: 6px 9px;
            border: 1px solid transparent;
            border-radius: 9px;
            background: transparent;
            color: #111827;
            font-size: 12px;
            line-height: 1.3;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .nav-link:hover {
            background: #f8fafc;
            border-color: #f1f5f9;
        }

        .nav-link.active {
            background: var(--active-bg);
            border-color: var(--active-border);
            color: var(--active-color);
        }

        .nav-icon {
            display: inline-grid;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            place-items: center;
            border: 1px solid var(--icon-border);
            border-radius: 8px;
            background: var(--icon-bg);
            color: var(--icon-color);
        }

        .nav-icon svg {
            display: block;
            width: 15px;
            height: 15px;
            fill: none;
            stroke: #000000;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .nav-link.active .nav-icon {
            background: #dbeafe;
            border-color: #bfdbfe;
        }

        .nav-text {
            flex: 1;
            min-width: 0;
            color: inherit;
        }

        .arrow {
            display: inline-grid;
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
            place-items: center;
            color: #475569;
            transition: transform 0.2s ease;
        }

        .nav-group.open > .nav-link .arrow {
            transform: rotate(90deg);
        }

        /* Expandable submenu */
        .nav-group {
            margin-top: 5px;
        }

        .submenu {
            display: none;
            margin: 4px 0 5px 35px;
            padding: 0 0 0 8px;
            border-left: 1px solid #edf0f5;
        }

        .nav-group.open > .submenu {
            display: block;
        }

        .submenu .nav-link {
            min-height: 35px;
            padding: 6px 9px;
            font-size: 12px;
        }

        .submenu .nav-link.active {
            background: #eaf2ff;
            border-color: transparent;
            color: #1d4ed8;
        }

        .submenu .nav-link.active::before {
            position: absolute;
            top: 7px;
            bottom: 7px;
            left: -9px;
            width: 2px;
            border-radius: 2px;
            background: #2563eb;
            content: "";
        }

        /* Sidebar footer */
        .sidebar-bottom {
            margin-top: auto;
            padding-top: 18px;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 7px;
            border-top: 1px solid #f1f5f9;
        }

        .avatar,
        .admin-avatar {
            display: grid;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            place-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 50%;
            background: #f1f3f7;
            color: #000000;
            font-size: 12px;
            font-weight: 700;
        }

        .user-copy {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-width: 0;
            gap: 3px;
        }

        .user-copy strong {
            overflow: hidden;
            color: #111827;
            font-size: 11px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-copy small {
            color: #64748b;
            font-size: 10px;
        }

        .logout {
            display: grid;
            width: 29px;
            height: 29px;
            place-items: center;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #000000;
            cursor: pointer;
        }

        .logout:hover {
            background: #f1f3f7;
        }

        .security-note {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 12px 8px 3px;
            color: #64748b;
            font-size: 10px;
        }

        .security-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
            background: #16a34a;
        }

        /* Top header */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 65px;
            padding: 10px 22px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .top-search {
            display: flex;
            align-items: center;
            gap: 10px;
            width: min(460px, 65%);
            height: 38px;
            margin: 0 auto;
            padding: 0 14px;
            border: 1px solid transparent;
            border-radius: 22px;
            background: #f3f4f6;
        }

        .top-search:focus-within {
            border-color: #bfdbfe;
            background: #ffffff;
        }

        .top-search > span {
            color: #64748b;
            font-size: 21px;
            line-height: 1;
        }

        .top-search input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: #111827;
            font-size: 12px;
        }

        .top-search input::placeholder {
            color: #94a3b8;
        }

        .topbar > .admin-avatar {
            flex-shrink: 0;
        }

        .page {
            padding: 20px;
        }

        /* Flash messages */
        .flash {
            margin-bottom: 15px;
            padding: 12px 15px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 13px;
        }

        .flash.success {
            border-color: #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }

        .flash.error {
            border-color: #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .flash ul {
            margin: 7px 0 0;
            padding-left: 20px;
        }

        /* Collapsed sidebar */
        .shell.sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
            padding-right: 9px;
            padding-left: 9px;
        }

        .shell.sidebar-collapsed .main {
            margin-left: var(--sidebar-collapsed-width);
        }

        .shell.sidebar-collapsed .brand-text,
        .shell.sidebar-collapsed .nav-heading,
        .shell.sidebar-collapsed .nav-text,
        .shell.sidebar-collapsed .arrow,
        .shell.sidebar-collapsed .submenu,
        .shell.sidebar-collapsed .user-copy,
        .shell.sidebar-collapsed .security-note,
        .shell.sidebar-collapsed .user-card form {
            display: none !important;
        }

        .shell.sidebar-collapsed .brand-row {
            justify-content: center;
            flex-wrap: wrap;
            padding-right: 0;
            padding-left: 0;
        }

        .shell.sidebar-collapsed .brand {
            justify-content: center;
        }

        .shell.sidebar-collapsed .sidebar-toggle {
            flex-basis: 100%;
        }

        .shell.sidebar-collapsed .nav-link {
            justify-content: center;
            gap: 0;
            padding: 5px;
        }

        .shell.sidebar-collapsed .nav-icon {
            flex-basis: 34px;
            width: 34px;
            height: 34px;
        }

        .shell.sidebar-collapsed .nav-group > .nav-link > span:first-child {
            display: flex;
            justify-content: center;
        }

        .shell.sidebar-collapsed .user-card {
            justify-content: center;
            padding-right: 0;
            padding-left: 0;
        }

        /* Responsive layout */
        @media (max-width: 760px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
                padding-right: 9px;
                padding-left: 9px;
            }

            .main {
                margin-left: var(--sidebar-collapsed-width);
            }

            .sidebar .brand-text,
            .sidebar .nav-heading,
            .sidebar .nav-text,
            .sidebar .arrow,
            .sidebar .submenu,
            .sidebar .user-copy,
            .sidebar .security-note,
            .sidebar .user-card form {
                display: none;
            }

            .sidebar .brand-row {
                justify-content: center;
                flex-wrap: wrap;
            }

            .sidebar .sidebar-toggle {
                flex-basis: 100%;
            }

            .sidebar .nav-link {
                justify-content: center;
                padding: 5px;
            }

            .sidebar .nav-group > .nav-link > span:first-child {
                display: flex;
                justify-content: center;
            }

            .topbar {
                padding-right: 12px;
                padding-left: 12px;
            }

            .page {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="shell" id="appShell">
        <aside class="sidebar" aria-label="Main navigation">
            <div class="brand-row">
                <a class="brand" href="{{ route('transmittals.index') }}">
                    <span class="brand-mark" aria-hidden="true">JK</span>

                    <span class="brand-text">
                        <strong>{{ config('ordo.office_name') }}</strong>
                        <small>ORDO INTERNAL SYSTEM</small>
                    </span>
                </a>

                <button
                    type="button"
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    aria-label="Toggle navigation"
                    aria-expanded="true"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

            <div class="nav-heading">
                <strong>Enterprise Menu</strong>
                <small>Navigation</small>
            </div>

            <nav class="sidebar-nav" aria-label="Enterprise menu">
                <a class="nav-link" href="#" title="Admin">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="10" cy="7" r="4"/>
                            <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </span>
                    <span class="nav-text">Admin</span>
                </a>

                <a class="nav-link" href="#" title="Town Hall">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="m3 11 18-5-5 12-4-5-5-2z"/>
                            <path d="m12 13 1 7"/>
                        </svg>
                    </span>
                    <span class="nav-text">Town Hall</span>
                </a>

                <a class="nav-link" href="#" title="Corporate">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <rect x="4" y="3" width="16" height="18" rx="1"/>
                            <path d="M9 21v-4h6v4M8 7h2m4 0h2M8 11h2m4 0h2"/>
                        </svg>
                    </span>
                    <span class="nav-text">Corporate</span>
                </a>

                <a class="nav-link" href="#" title="Policies">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path d="M14 2v6h6M8 13h8M8 17h8"/>
                        </svg>
                    </span>
                    <span class="nav-text">Policies</span>
                </a>

                <a class="nav-link" href="#" title="Finance">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                            <path d="M2 10h20M6 15h4"/>
                        </svg>
                    </span>
                    <span class="nav-text">Finance</span>
                </a>

                <a class="nav-link" href="#" title="Human Capital">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2"/>
                        </svg>
                    </span>
                    <span class="nav-text">Human Capital</span>
                </a>

                <a class="nav-link" href="#" title="Marketing">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M8 14c1 2 3 3 5 2s3-3 2-5-3-3-5-2-3 3-2 5z"/>
                            <circle cx="12" cy="12" r="1"/>
                        </svg>
                    </span>
                    <span class="nav-text">Marketing</span>
                    <span class="arrow" aria-hidden="true">›</span>
                </a>

                <a class="nav-link" href="#" title="Sales">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 3v18h18"/>
                            <path d="m7 14 4-4 3 3 6-7"/>
                        </svg>
                    </span>
                    <span class="nav-text">Sales</span>
                    <span class="arrow" aria-hidden="true">›</span>
                </a>

                <a class="nav-link" href="#" title="Accounts">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <circle cx="9" cy="11" r="2"/>
                            <path d="M6 16c.7-2 5.3-2 6 0m3-5h3m-3 4h3"/>
                        </svg>
                    </span>
                    <span class="nav-text">Accounts</span>
                    <span class="arrow" aria-hidden="true">›</span>
                </a>

                <div class="nav-group open" id="operationsGroup">
                    <button
                        type="button"
                        class="nav-link {{ request()->routeIs('transmittals.*') ? 'active' : '' }}"
                        id="operationsToggle"
                        aria-expanded="true"
                        aria-controls="operationsSubmenu"
                    >
                        <span style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                            <span class="nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                                    <path d="M3 9h18M8 4v5m8-5v5M8 13h3m2 0h3M8 16h3"/>
                                </svg>
                            </span>
                            <span class="nav-text">Operations</span>
                        </span>
                        <span class="arrow" aria-hidden="true">›</span>
                    </button>

                    <div class="submenu" id="operationsSubmenu">
                        <a class="nav-link" href="#" title="Activities">
                            <span class="nav-text">Activities</span>
                        </a>

                        <a class="nav-link" href="#" title="Regular">
                            <span class="nav-text">Regular</span>
                        </a>

                        <a class="nav-link" href="#" title="Project">
                            <span class="nav-text">Project</span>
                        </a>

                        <a
                            class="nav-link {{ request()->routeIs('transmittals.*') ? 'active' : '' }}"
                            href="{{ route('transmittals.index') }}"
                            title="Transmittal"
                            aria-current="{{ request()->routeIs('transmittals.*') ? 'page' : 'false' }}"
                        >
                            <span class="nav-text">Transmittal</span>
                        </a>

                        <a class="nav-link" href="#" title="Visitor Log">
                            <span class="nav-text">Visitor Log</span>
                        </a>
                    </div>
                </div>
            </nav>

            <div class="sidebar-bottom">
                @auth
                    <div class="user-card">
                        <span class="avatar" aria-hidden="true">
                            {{ mb_substr(auth()->user()->name, 0, 1) }}
                        </span>

                        <span class="user-copy">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>Administrator</small>
                        </span>

                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="logout" type="submit" aria-label="Sign out">
                                <svg width="17" height="17" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round"
                                     aria-hidden="true">
                                    <path d="M10 17l5-5-5-5M15 12H3"/>
                                    <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                @else
                    <a class="admin-avatar" href="{{ route('login') }}"
                       aria-label="Administrator sign in">M</a>
                @endauth

                <div class="security-note">
                    <span class="security-dot" aria-hidden="true"></span>
                    <span class="nav-text">Internal transmittal system</span>
                </div>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <form class="top-search" method="get" action="{{ route('transmittals.index') }}">
                    <span aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search"
                        aria-label="Search transmittals"
                    >
                </form>

                @auth
                    <span class="admin-avatar"
                          aria-label="Signed in as {{ auth()->user()->name }}">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </span>
                @else
                    <a class="admin-avatar" href="{{ route('login') }}"
                       aria-label="Administrator sign in">J</a>
                @endauth
            </header>

            <div class="page">
                @if (session('status'))
                    <div class="flash success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="flash error" role="alert">
                        <strong>Some details need attention.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <script>
        (() => {
            const shell = document.getElementById('appShell');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const operationsGroup = document.getElementById('operationsGroup');
            const operationsToggle = document.getElementById('operationsToggle');

            if (sidebarToggle && shell) {
                sidebarToggle.addEventListener('click', () => {
                    const collapsed = shell.classList.toggle('sidebar-collapsed');
                    sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
                });
            }

            if (operationsToggle && operationsGroup) {
                operationsToggle.addEventListener('click', () => {
                    const isOpen = operationsGroup.classList.toggle('open');
                    operationsToggle.setAttribute('aria-expanded', String(isOpen));
                });
            }
        })();
    </script>

    @stack('scripts')
</body>
</html>
