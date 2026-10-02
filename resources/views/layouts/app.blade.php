<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'John Kelly & Company | CRM')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/image.png') }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <style>
        [x-cloak] { display: none !important; }

        .ql-toolbar.ql-snow {
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            border-color: rgb(209 213 219);
        }

        .ql-container.ql-snow {
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
            border-color: rgb(209 213 219);
            min-height: 260px;
            font-size: 14px;
        }

        .ql-editor {
            min-height: 260px;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif
    @stack('styles')
</head>

<body class="bg-gray-50 font-sans">
    @php
        $user = Auth::user();
        $notificationsEnabled = \Illuminate\Support\Facades\Schema::hasTable('notifications');
        $unreadNotifications = $notificationsEnabled
            ? $user->unreadNotifications()->latest()->take(8)->get()
            : collect();
        $unreadNotificationCount = $notificationsEnabled ? $user->unreadNotifications()->count() : 0;
        $canSeeCrmModules = !$user->isClient();

        $canSeeAdminIcon =
            $user->isSuperAdmin() ||
            $user->isAdmin() ||
            $user->hasPermission('access_admin_dashboard') ||
            $user->hasPermission('approve_townhall') ||
            $user->hasPermission('approve_corporate') ||
            $user->hasPermission('manage_users');

        $adminLandingRoute = null;

        if ($user->hasPermission('manage_users')) {
            $adminLandingRoute = route('admin.users');
        } elseif ($user->isSuperAdmin() || $user->isAdmin()) {
            $adminLandingRoute = route('admin.finance.dashboard');
        } elseif ($user->hasPermission('access_admin_dashboard') || $user->hasPermission('approve_townhall')) {
            $adminLandingRoute = route('admin.dashboard');
        } elseif ($user->hasPermission('approve_corporate')) {
            $adminLandingRoute = \Illuminate\Support\Facades\Route::has('admin.correspondence.dashboard')
                ? route('admin.correspondence.dashboard')
                : route('admin.corporate.dashboard');
        }

        $isHumanCapitalSection =
            request()->is('human-capital') ||
            request()->is('human-capital/*');

        $canSeeHumanCapital =
            $user->hasPermission('access_human_capital') ||
            collect([
                'access_hc_organizational',
                'access_hc_payroll',
                'access_hc_employee_profile',
                'access_hc_recruitment',
                'access_hc_onboarding',
                'access_hc_deployment',
                'access_hc_offboarding',
                'access_hc_my_hc',
                'access_hc_attendance',
                'access_hc_obf',
                'access_hc_employee_requests',
                'access_hc_employee_relations',
                'access_hc_memos',
                'access_hc_training',
                'access_hc_performance',
                'access_hc_awards',
            ])->contains(fn ($permission) => $user->hasPermission($permission)) ||
            (bool) $user ||
            $user->isAdmin() ||
            $user->isSuperAdmin();

        $canManageHumanCapital = $user->isAdmin() || $user->isSuperAdmin();
        $canAccessHc = fn (string $permission, bool $employeeOwnAccess = false) =>
            $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->hasPermission($permission)
            || $employeeOwnAccess;
        $canAccessMyHc = fn (string $permission) => $canAccessHc($permission, true) || $user->hasPermission('access_hc_my_hc');

        $humanCapitalLandingRoute = $canAccessHc('access_hc_organizational')
            ? route('human-capital.organizational')
            : ($canAccessHc('access_hc_employee_profile', true)
                ? route('human-capital.employee-profile')
                : route('human-capital.attendance'));

        $canSeeFinance =
            $user->isAdmin() ||
            $user->isSuperAdmin() ||
            $user->hasPermission('access_finance') ||
            $user->hasPermission('create_finance') ||
            $user->hasPermission('approve_finance') ||
            collect([
                'access_finance_supplier',
                'access_finance_service',
                'access_finance_product',
                'access_finance_chart_account',
                'access_finance_bank_account',
                'access_finance_pr',
                'access_finance_po',
                'access_finance_ca',
                'access_finance_lr',
                'access_finance_err',
                'access_finance_dv',
                'access_finance_pda',
                'access_finance_crf',
                'access_finance_ibtf',
                'access_finance_arf',
            ])->contains(fn ($permission) => $user->hasPermission($permission));


        $activeSidebarGroup = match (true) {
            request()->routeIs('products*'), request()->routeIs('services*') => 'marketing',
            request()->routeIs('deals*'), request()->routeIs('sales-marketing*') => 'sales',
            request()->routeIs('contacts*'), request()->routeIs('company*') => 'accounts',
            request()->routeIs('activities*'), request()->routeIs('regular*'), request()->routeIs('project*'), request()->routeIs('transmittal*') => 'operations',
            default => '',
        };

        $isMarketingActive = $activeSidebarGroup === 'marketing';
        $isSalesActive = $activeSidebarGroup === 'sales';
        $isAccountsActive = $activeSidebarGroup === 'accounts';
        $isOperationsActive = $activeSidebarGroup === 'operations';
    @endphp

    <!-- HEADER -->
    <header class="h-16 bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="h-full px-4 flex items-center justify-between">

            <div class="flex items-center gap-3 w-[260px]">
                <img src="/images/imaglogo.png" class="h-10 w-auto" alt="Logo">
            </div>

            <div class="flex-1 flex justify-center px-6">
                <div class="relative w-full max-w-xl">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input
                        type="text"
                        placeholder="Search"
                        class="w-full bg-gray-100 focus:bg-white border border-transparent focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2 text-sm outline-none transition"
                    >
                </div>
            </div>

            <div class="w-[260px] flex justify-end">
                <div class="flex items-center gap-4">

                    @include('partials.notification-bell')

                    <div x-data="{ open:false }" class="relative">
                        <button
                            @click="open=!open"
                            class="h-9 w-9 rounded-full bg-gray-200 flex items-center justify-center text-gray-700 font-semibold hover:ring-2 hover:ring-gray-300 transition"
                        >
                            {{ strtoupper(substr(Auth::user()->name,0,1)) }}
                        </button>

                        <div
                            x-show="open"
                            @click.outside="open=false"
                            x-transition
                            class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden"
                            style="display:none;"
                        >
                            <div class="px-4 py-3 border-b text-sm">
                                <p class="font-semibold text-gray-800">{{ Auth::user()->name }}</p>
                                <p class="text-gray-400 text-xs">{{ Auth::user()->role }}</p>
                            </div>


                            <a
                                href="{{ route('password.change') }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            >
                                <i class="fas fa-key mr-2"></i>
                                Change Password
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50 transition"
                                >
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </header>

    <div class="flex h-[calc(100vh-4rem)]">

        <!-- ENTERPRISE SIDEBAR -->
        <aside
            x-data="{
                sidebarCollapsed: localStorage.getItem('enterpriseSidebarCollapsed') === 'true',
                sidebarHover: false,
                activeGroup: @js($activeSidebarGroup),
                openGroup: @js($activeSidebarGroup) || localStorage.getItem('enterpriseSidebarOpenGroup') || '',
                get expanded() {
                    return !this.sidebarCollapsed || this.sidebarHover;
                },
                toggleSidebar() {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    this.sidebarHover = false;
                    localStorage.setItem('enterpriseSidebarCollapsed', this.sidebarCollapsed ? 'true' : 'false');
                },
                toggleGroup(group) {
                    if (!this.expanded) return;
                    this.openGroup = this.openGroup === group ? '' : group;
                    localStorage.setItem('enterpriseSidebarOpenGroup', this.openGroup);
                },
                isOpen(group) {
                    return this.expanded && this.openGroup === group;
                }
            }"
            @mouseenter="if (sidebarCollapsed) sidebarHover = true"
            @mouseleave="if (sidebarCollapsed) sidebarHover = false"
            :class="expanded ? 'w-72' : 'w-20'"
            class="bg-white border-r border-gray-200 flex flex-col transition-all duration-300 ease-in-out overflow-hidden"
        >
            <div class="px-3 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="toggleSidebar()"
                        class="h-10 w-10 shrink-0 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center border border-blue-100 hover:bg-blue-100 transition"
                        title="Collapse / expand menu"
                    >
                        <i class="fas fa-layer-group text-sm"></i>
                    </button>

                    <div x-show="expanded" x-transition.opacity.duration.200ms class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-gray-900 whitespace-nowrap">Enterprise Menu</p>
                        <p class="text-xs text-gray-400 whitespace-nowrap">Navigation</p>
                    </div>

                    <button
                        x-show="expanded"
                        x-transition.opacity.duration.200ms
                        type="button"
                        @click="toggleSidebar()"
                        class="h-8 w-8 shrink-0 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition"
                        title="Collapse sidebar"
                    >
                        <i class="fas fa-angles-left text-xs" :class="sidebarCollapsed ? 'rotate-180' : ''"></i>
                    </button>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto p-3 no-scrollbar">
                <div class="space-y-1 text-sm">
                    @if($canSeeAdminIcon && $adminLandingRoute)
                        <a href="{{ $adminLandingRoute }}"
                           title="Admin"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ request()->routeIs('admin.*') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ request()->routeIs('admin.*') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-user-shield text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Admin</span>
                        </a>
                    @endif

                    @if(Auth::user()->hasPermission('access_townhall'))
                        <a href="{{ route('townhall') }}"
                           title="Town Hall"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ request()->routeIs('townhall*') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ request()->routeIs('townhall*') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-bullhorn text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Town Hall</span>
                        </a>
                    @endif

                    @if(Auth::user()->hasPermission('access_corporate'))
                        <a href="{{ route('corporate') }}"
                           title="Corporate"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ request()->routeIs('corporate*') || request()->routeIs('stock-transfer-book*') || request()->routeIs('bir-tax*') || request()->routeIs('natgov*') || request()->routeIs('notices*') || request()->routeIs('minutes*') || request()->routeIs('resolutions*') || request()->routeIs('secretary-certificates*') || request()->routeIs('correspondence*') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ request()->routeIs('corporate*') || request()->routeIs('stock-transfer-book*') || request()->routeIs('bir-tax*') || request()->routeIs('natgov*') || request()->routeIs('notices*') || request()->routeIs('minutes*') || request()->routeIs('resolutions*') || request()->routeIs('secretary-certificates*') || request()->routeIs('correspondence*') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-building text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Corporate</span>
                        </a>
                    @endif

                    @if($canSeeCrmModules && Auth::user()->hasPermission('access_policies'))
                        <a href="{{ route('policies.index') }}"
                           title="Policies"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ request()->routeIs('policies*') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ request()->routeIs('policies*') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-file-contract text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Policies</span>
                        </a>
                    @endif

                    @if($canSeeFinance)
                        <a href="{{ route('finance') }}"
                           title="Finance"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ request()->routeIs('finance*') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ request()->routeIs('finance*') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-coins text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Finance</span>
                        </a>
                    @endif

                    @if($user->isClient())
                        <a href="{{ route('client.official-business.trip-tickets.page') }}"
                           title="Trip Tickets"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ request()->routeIs('client.official-business.trip-tickets.page') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg bg-gray-100 flex items-center justify-center"><i class="fas fa-route text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Trip Tickets</span>
                        </a>
                    @endif

                    @if($canSeeHumanCapital)
                        <a href="{{ $humanCapitalLandingRoute }}"
                           title="Human Capital"
                           :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                           class="flex items-center gap-3 py-2.5 rounded-xl transition border {{ $isHumanCapitalSection ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ $isHumanCapitalSection ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-user-tie text-xs"></i></span>
                            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 whitespace-nowrap">Human Capital</span>
                        </a>
                    @endif

                    @if($canSeeCrmModules && (Auth::user()->hasPermission('access_product') || Auth::user()->hasPermission('access_services')))
                        <div class="space-y-1">
                            <button type="button" @click="toggleGroup('marketing')" title="Marketing"
                                :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                                class="w-full flex items-center gap-3 py-2.5 rounded-xl transition border {{ $isMarketingActive ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ $isMarketingActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-bullseye text-xs"></i></span>
                                <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 text-left whitespace-nowrap">Marketing</span>
                                <i x-show="expanded" class="fas fa-chevron-right text-[11px] transition-transform duration-200" :class="isOpen('marketing') ? 'rotate-90' : ''"></i>
                            </button>
                            <div x-cloak x-show="isOpen('marketing')" x-collapse.duration.200ms class="ml-11 space-y-1 border-l border-gray-100 pl-3">
                                @if(Auth::user()->hasPermission('access_product'))
                                    <a href="{{ route('products.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('products*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Product</a>
                                @endif
                                @if(Auth::user()->hasPermission('access_services'))
                                    <a href="{{ route('services.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('services*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Services</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if(
    $canSeeCrmModules
    && (
        Auth::user()->hasPermission('access_deals')
        || Auth::user()->hasPermission('access_sales_marketing')
    )
)
    <div class="space-y-1">
        <button type="button" @click="toggleGroup('sales')" title="Sales"
            :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
            class="w-full flex items-center gap-3 py-2.5 rounded-xl transition border {{ $isSalesActive ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
            <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ $isSalesActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
                <i class="fas fa-chart-line text-xs"></i>
            </span>

            <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 text-left whitespace-nowrap">
                Sales
            </span>

            <i x-show="expanded" class="fas fa-chevron-right text-[11px] transition-transform duration-200" :class="isOpen('sales') ? 'rotate-90' : ''"></i>
        </button>

        <div x-cloak x-show="isOpen('sales')" x-collapse.duration.200ms class="ml-11 space-y-1 border-l border-gray-100 pl-3">
            @if(Auth::user()->hasPermission('access_deals'))
                <a href="{{ route('deals.index') }}"
                   class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('deals*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    Deals
                </a>
            @endif

            @if(Auth::user()->hasPermission('access_sales_marketing') && \Illuminate\Support\Facades\Route::has('sales-marketing.index'))
                <a href="{{ route('sales-marketing.index') }}"
                   class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('sales-marketing*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    Incentive Management
                </a>
            @endif
        </div>
    </div>
@endif

                    @if($canSeeCrmModules && (Auth::user()->hasPermission('access_contacts') || Auth::user()->hasPermission('access_company')))
                        <div class="space-y-1">
                            <button type="button" @click="toggleGroup('accounts')" title="Accounts"
                                :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                                class="w-full flex items-center gap-3 py-2.5 rounded-xl transition border {{ $isAccountsActive ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ $isAccountsActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-address-book text-xs"></i></span>
                                <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 text-left whitespace-nowrap">Accounts</span>
                                <i x-show="expanded" class="fas fa-chevron-right text-[11px] transition-transform duration-200" :class="isOpen('accounts') ? 'rotate-90' : ''"></i>
                            </button>
                            <div x-cloak x-show="isOpen('accounts')" x-collapse.duration.200ms class="ml-11 space-y-1 border-l border-gray-100 pl-3">
                                @if(Auth::user()->hasPermission('access_contacts'))
                                    <a href="{{ route('contacts.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('contacts*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Contact</a>
                                @endif
                                @if(Auth::user()->hasPermission('access_company'))
                                    <a href="{{ route('company.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('company*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Company</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($canSeeCrmModules && (Auth::user()->hasPermission('access_activities') || Auth::user()->hasPermission('access_regular') || Auth::user()->hasPermission('access_project') || Auth::user()->hasPermission('access_transmittal')))
                        <div class="space-y-1">
                            <button type="button" @click="toggleGroup('operations')" title="Operations"
                                :class="expanded ? 'justify-start px-3' : 'justify-center px-0'"
                                class="w-full flex items-center gap-3 py-2.5 rounded-xl transition border {{ $isOperationsActive ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <span class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center {{ $isOperationsActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}"><i class="fas fa-diagram-project text-xs"></i></span>
                                <span x-show="expanded" x-transition.opacity.duration.200ms class="flex-1 text-left whitespace-nowrap">Operations</span>
                                <i x-show="expanded" class="fas fa-chevron-right text-[11px] transition-transform duration-200" :class="isOpen('operations') ? 'rotate-90' : ''"></i>
                            </button>
                            <div x-cloak x-show="isOpen('operations')" x-collapse.duration.200ms class="ml-11 space-y-1 border-l border-gray-100 pl-3">
                                @if(Auth::user()->hasPermission('access_activities'))
                                    @if(\Illuminate\Support\Facades\Route::has('activities'))
                                        <a href="{{ route('activities') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('activities*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Activities</a>
                                    @endif
                                @endif

                                @if(Auth::user()->hasPermission('access_regular'))
                                    <a href="{{ route('regular.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('regular*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Regular</a>
                                @endif
                                @if(Auth::user()->hasPermission('access_project'))
                                    <a href="{{ route('project.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('project*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Project</a>
                                @endif
                                @if(Auth::user()->hasPermission('access_transmittal'))
                                    <a href="{{ route('transmittal.index') }}" class="block rounded-lg px-3 py-2 transition {{ request()->routeIs('transmittal*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Transmittal</a>
                                @endif

                                <div x-data="{ open: {{ request()->routeIs('visitor-log*') ? 'true' : 'false' }} }" class="space-y-1">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition
                                        {{ request()->routeIs('visitor-log*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                        <span>Visitor Log</span>
                                        <i class="fas fa-chevron-down text-[11px]" :class="open ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">

                                        <div x-data="{ open: {{ request()->routeIs('front-desk*') ? 'true' : 'false' }} }">
                                            <button type="button" @click="open = !open"
                                                class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition
                                                {{ request()->routeIs('front-desk*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                                <span>Front Desk</span>
                                                <i class="fas fa-chevron-down text-[11px]" :class="open ? 'rotate-180' : ''"></i>
                                            </button>

                                            <div x-cloak x-show="open" x-transition class="pl-3">
                                                <a href="#"
                                                   class="block px-3 py-2 rounded-lg transition text-gray-600 hover:bg-gray-50">
                                                    Visit Records
                                                </a>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </nav>
        </aside>

        <!-- SECOND SIDEBAR -->
        @if(request()->routeIs('townhall*'))
            <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Town Hall</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="space-y-1 text-sm">
                        <a href="{{ route('townhall') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('townhall*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Communications
                        </a>

                        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1">
                            <a href="{{ route('townhall.department') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('townhall.department') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Department
                            </a>

                            <a href="{{ route('townhall.attachments') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('townhall.attachments') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Attachments
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

        @elseif(request()->routeIs('admin.*') && $canSeeAdminIcon)
            <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">

                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        Admin Panel
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="space-y-1 text-sm">

                        {{-- ADMIN CONTROLS --}}
                        @if(Auth::user()->hasPermission('manage_users'))
                            <a href="{{ route('admin.users') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.users') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Users
                            </a>

                            <a href="{{ route('admin.role-permissions') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.role-permissions') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Role Permissions
                            </a>

                            <a href="{{ route('admin.user-permissions') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.user-permissions') || request()->routeIs('admin.user-permissions.edit') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                User Permissions
                            </a>

                            @if(auth()->user()?->isSuperAdmin())
    <a href="{{ route('admin.account-audit-trail') }}"
       class="block px-3 py-2 rounded-lg transition
       {{ request()->routeIs('admin.account-audit-trail') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
        Account Audit Trail
    </a>
@endif

                            <div class="my-3 border-t border-gray-100"></div>
                        @endif

                        {{-- ENTERPRISE MODULE ORDER --}}
                        @if(Auth::user()->hasPermission('access_admin_dashboard') || Auth::user()->hasPermission('approve_townhall'))
                            <a href="{{ route('admin.dashboard') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Town Hall
                            </a>
                        @endif

                        @if(Auth::user()->hasPermission('approve_corporate'))
                            <div
                                x-data="{
                                    open: {{
                                        request()->routeIs('admin.corporate.dashboard')
                                        || request()->routeIs('admin.correspondence.*')
                                            ? 'true'
                                            : 'false'
                                    }}
                                }"
                                class="space-y-1"
                            >
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                    {{
                                        request()->routeIs('admin.corporate.dashboard')
                                        || request()->routeIs('admin.correspondence.*')
                                            ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold'
                                            : 'border-transparent hover:bg-gray-100 text-gray-700'
                                    }}"
                                >
                                    <span>Corporate</span>
                                    <i class="fas fa-chevron-down text-[11px] transition-transform duration-200"
                                    :class="open ? 'rotate-180' : ''"></i>
                                </button>

                                <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                    <a href="{{ route('admin.corporate.dashboard') }}"
                                    class="block px-3 py-2 rounded-lg transition
                                    {{ request()->routeIs('admin.corporate.dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                        Corporate Dashboard
                                    </a>

                                    @if(\Illuminate\Support\Facades\Route::has('admin.correspondence.dashboard'))
                                        <a href="{{ route('admin.correspondence.dashboard') }}"
                                        class="block px-3 py-2 rounded-lg transition
                                        {{ request()->routeIs('admin.correspondence.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Correspondence
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <a href="{{ route('admin.policies.index') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('admin.policies.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Policies
                        </a>

                        @if(Auth::user()->isSuperAdmin() || Auth::user()->isAdmin() || Auth::user()->hasPermission('manage_users'))
                            <a href="{{ route('admin.finance.dashboard') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.finance.dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Finance
                            </a>
                        @endif

                        @if(Auth::user()->isAdmin() || Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('access_admin_dashboard'))
                            <a href="{{ route('admin.human-capital.dashboard') }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.human-capital.dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Human Capital
                            </a>
                        @endif

                        @if((Auth::user()->isAdmin() || Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('approve_corporate')) && \Illuminate\Support\Facades\Route::has('admin.transmittal.dashboard'))
                            <a href="{{ route('admin.transmittal.dashboard') }}"
                            class="block px-3 py-2 rounded-lg transition
                            {{ request()->routeIs('admin.transmittal.dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Transmittal
                            </a>
                        @endif

                        @if(Auth::user()->hasPermission('access_admin_dashboard') || Auth::user()->hasPermission('approve_townhall'))
                            {{-- Marketing --}}
                            <a href="{{ route('admin.dashboard.section', ['section' => 'products']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'products' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Products
                            </a>

                            <a href="{{ route('admin.dashboard.section', ['section' => 'services']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'services' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Services
                            </a>

                            {{-- Sales --}}
                            <a href="{{ route('admin.dashboard.section', ['section' => 'deals']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'deals' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Deals
                            </a>

                            {{-- Accounts --}}
                            <a href="{{ route('admin.dashboard.section', ['section' => 'contacts']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'contacts' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Contacts
                            </a>

                            <a href="{{ route('admin.dashboard.section', ['section' => 'company']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'company' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Company
                            </a>

                            {{-- Operations --}}

                            <a href="{{ route('admin.dashboard.section', ['section' => 'regular']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'regular' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Regular
                            </a>

                            <a href="{{ route('admin.dashboard.section', ['section' => 'project']) }}"
                               class="block px-3 py-2 rounded-lg transition
                               {{ request()->routeIs('admin.dashboard.section') && request()->route('section') === 'project' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Project
                            </a>
                        @endif

                    </div>
                </div>

            </aside>

        @elseif(request()->routeIs('finance*'))
            <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Finance</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="space-y-1 text-sm">
                        <a href="{{ route('finance', ['module' => 'supplier']) }}" data-finance-module="supplier"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module', 'supplier') === 'supplier' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Supplier
                        </a>
                        <a href="{{ route('finance', ['module' => 'service']) }}" data-finance-module="service"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'service' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Service
                        </a>
                        <a href="{{ route('finance', ['module' => 'product']) }}" data-finance-module="product"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'product' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Product
                        </a>
                        <a href="{{ route('finance', ['module' => 'chart_account']) }}" data-finance-module="chart_account"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'chart_account' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Chart of Accounts
                        </a>
                        <a href="{{ route('finance', ['module' => 'bank_account']) }}" data-finance-module="bank_account"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'bank_account' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Bank Accounts
                        </a>
                        <a href="{{ route('finance', ['module' => 'pr']) }}" data-finance-module="pr"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'pr' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Purchase Request
                        </a>
                        <a href="{{ route('finance', ['module' => 'po']) }}" data-finance-module="po"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'po' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Purchase Order
                        </a>
                        <a href="{{ route('finance', ['module' => 'ca']) }}" data-finance-module="ca"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'ca' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Cash Advance
                        </a>
                        <a href="{{ route('finance', ['module' => 'lr']) }}" data-finance-module="lr"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'lr' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Liquidation Report
                        </a>
                        <a href="{{ route('finance', ['module' => 'err']) }}" data-finance-module="err"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'err' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Expense Reimbursement Request
                        </a>
                        <a href="{{ route('finance', ['module' => 'dv']) }}" data-finance-module="dv"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'dv' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Disbursement Voucher
                        </a>
                        <a href="{{ route('finance', ['module' => 'pda']) }}" data-finance-module="pda"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'pda' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Payroll Disbursement Authorization
                        </a>
                        <a href="{{ route('finance', ['module' => 'crf']) }}" data-finance-module="crf"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'crf' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Cash Return Form
                        </a>
                        <a href="{{ route('finance', ['module' => 'ibtf']) }}" data-finance-module="ibtf"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'ibtf' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Interbank Fund Transfer Form
                        </a>
                        <a href="{{ route('finance', ['module' => 'arf']) }}" data-finance-module="arf"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request('module') === 'arf' ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Asset Registration Form
                        </a>
                    </div>
                </div>
            </aside>

        @elseif($isHumanCapitalSection && $canSeeHumanCapital)
            <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Human Capital</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="space-y-1 text-sm">

                        @if($canAccessHc('access_hc_organizational'))
                            <a href="{{ route('human-capital.organizational') }}"
                               class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/organizational') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Organizational
                            </a>
                        @endif

                        @if($canAccessHc('access_hc_payroll'))
                            <a href="{{ route('human-capital.payroll') }}"
                               class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/payroll') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Payroll
                            </a>
                        @endif

                        @if($canAccessHc('access_hc_employee_profile', true))
                            <a href="{{ route('human-capital.employee-profile') }}"
                               class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/employee-profile') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Employee Profile
                            </a>
                        @endif

                        @if($canAccessHc('access_hc_recruitment'))
                            {{-- RECRUITMENT MODULE --}}
<div
    x-data="{
        open: {{
            request()->is('human-capital/recruitment')
            || request()->is('human-capital/recruitment/*')
            || request()->routeIs('assessment-questions.*')
            || request()->routeIs('assessment-types.*')
                ? 'true'
                : 'false'
        }}
    }"
    class="space-y-1"
>
    <div class="flex items-center gap-1">
        {{-- Main Recruitment Link --}}
        <a href="{{ route('human-capital.recruitment') }}"
           class="flex-1 block px-3 py-2 rounded-lg transition border
           {{
                request()->is('human-capital/recruitment')
                    ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold'
                    : 'border-transparent hover:bg-gray-100 text-gray-700'
           }}">
            Recruitment
        </a>

        {{-- Dropdown Arrow --}}
        <button
            type="button"
            @click="open = !open"
            class="w-9 h-9 rounded-lg flex items-center justify-center transition border
            {{
                request()->routeIs('assessment-questions.*')
                || request()->routeIs('assessment-types.*')
                    ? 'bg-blue-50 text-blue-700 border-blue-100'
                    : 'border-transparent hover:bg-gray-100 text-gray-500'
            }}"
        >
            <i class="fas fa-chevron-down text-[11px] transition-transform duration-200"
               :class="open ? 'rotate-180' : ''"></i>
        </button>
    </div>

    {{-- Dropdown Content --}}
    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
        <a href="{{ route('assessment-questions.index') }}"
           class="block px-3 py-2 rounded-lg transition text-sm
           {{
                request()->routeIs('assessment-questions.*')
                || request()->routeIs('assessment-types.*')
                    ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold'
                    : 'hover:bg-gray-100 text-gray-700'
           }}">
            Assessment Questionnaire Editor
        </a>
    </div>
</div>
                        @endif

                        @if($canAccessHc('access_hc_onboarding'))
                            <a href="{{ route('human-capital.onboarding') }}"
                               class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/onboarding') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                On Boarding
                            </a>
                        @endif

                        @if($canAccessHc('access_hc_deployment'))
                            <a href="{{ route('human-capital.deployment') }}"
                               class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/deployment') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                Deployment
                            </a>
                        @endif

                        @if($canAccessMyHc('access_hc_attendance') || $canAccessMyHc('access_hc_obf') || $canAccessMyHc('access_hc_employee_requests') || $canAccessMyHc('access_hc_employee_relations') || $canAccessMyHc('access_hc_memos') || $canAccessMyHc('access_hc_training') || $canAccessMyHc('access_hc_performance') || $canAccessMyHc('access_hc_awards'))
                        {{-- MY HC MODULE --}}
                        <div
                            x-data="{
                                open: {{
                                    request()->is('human-capital/attendance')
                                    || request()->is('human-capital/obf')
                                    || request()->is('human-capital/employee-requests')
                                    || request()->is('human-capital/employee-requests/*')
                                    || request()->is('human-capital/employee-relations')
                                    || request()->is('human-capital/memos')
                                    || request()->is('human-capital/training')
                                    || request()->is('human-capital/performance')
                                    || request()->is('human-capital/awards')
                                        ? 'true'
                                        : 'false'
                                }}
                            }"
                            class="space-y-1"
                        >
                            <button
                                type="button"
                                @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                {{
                                    request()->is('human-capital/attendance')
                                    || request()->is('human-capital/obf')
                                    || request()->is('human-capital/employee-requests')
                                    || request()->is('human-capital/employee-requests/*')
                                    || request()->is('human-capital/employee-relations')
                                    || request()->is('human-capital/memos')
                                    || request()->is('human-capital/training')
                                    || request()->is('human-capital/performance')
                                    || request()->is('human-capital/awards')
                                        ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold'
                                        : 'border-transparent hover:bg-gray-100 text-gray-700'
                                }}"
                            >
                                <span>My HC</span>
                                <i class="fas fa-chevron-down text-[11px] transition-transform duration-200"
                                   :class="open ? 'rotate-180' : ''"></i>
                            </button>

                            <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">

                                @if($canAccessMyHc('access_hc_attendance'))
                                <a href="{{ route('human-capital.attendance') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/attendance') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Attendance
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_obf'))
                                <a href="{{ route('human-capital.obf') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/obf') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Official Business Trip Form
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_employee_requests'))
                                <a href="{{ route('human-capital.employee-requests.index') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/employee-requests') || request()->is('human-capital/employee-requests/*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Employee Requests
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_employee_relations'))
                                <a href="{{ route('human-capital.employee-relations') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/employee-relations') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Employee Relations
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_memos'))
                                <a href="{{ route('human-capital.memos') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/memos') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Memos
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_training'))
                                <a href="{{ route('human-capital.training') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/training') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Training
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_performance'))
                                <a href="{{ route('human-capital.performance') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/performance') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Performance
                                </a>
                                @endif

                                @if($canAccessMyHc('access_hc_awards'))
                                <a href="{{ route('human-capital.awards') }}"
                                   class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/awards') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Awards
                                </a>
                                @endif

                            </div>
                        </div>
                        @endif

                        @if($canAccessHc('access_hc_offboarding'))
                            <a href="{{ route('human-capital.offboarding') }}"
                               class="block px-3 py-2 rounded-lg transition {{ request()->is('human-capital/offboarding') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                OffBoarding
                            </a>
                        @endif
                    </div>
                </div>
            </aside>

        @elseif(
            $canSeeCrmModules
            && Auth::user()->hasPermission('access_sales_marketing')
            && request()->routeIs('sales-marketing*')
        )
            <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Incentive Management</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="space-y-1 text-sm">
                        <a href="{{ route('sales-marketing.index') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('sales-marketing.index') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Dashboard
                        </a>

                        <a href="{{ route('sales-marketing.earners.index') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('sales-marketing.earners.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Incentive Earners
                        </a>

                        <a href="{{ route('sales-marketing.ida.index') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('sales-marketing.ida.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            IDA Records
                        </a>
                        @if(Auth::user()->hasPermission('approve_sales_marketing'))
                    <a href="{{ route('sales-marketing.payouts.index') }}"
                       class="block px-3 py-2 rounded-lg transition
                       {{ request()->routeIs('sales-marketing.payouts.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                        Payout Requests
                    </a>
                @endif
                    </div>
                </div>
            </aside>

        @elseif(
            $canSeeCrmModules
            && Auth::user()->hasPermission('access_company')
            && request()->routeIs('company.*')
            && ! request()->routeIs('company.index')
        )
            <aside class="w-72 bg-white border-r border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Company</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="space-y-1 text-sm">
                        @php
                            $currentCompany = request()->route('company');
                            $currentCompanyId = is_object($currentCompany)
                                ? $currentCompany->id
                                : ($currentCompany ?: request()->segment(2));
                            $hasCompanyContext = filled($currentCompanyId);

                            $companyCorporateOpen =
                                request()->routeIs('company.lgu*')
                                || request()->routeIs('company.accounting*')
                                || request()->routeIs('company.banking*')
                                || request()->routeIs('company.legal*')
                                || request()->routeIs('company.operations*')
                                || request()->routeIs('company.correspondence*')
                                || request()->routeIs('company.bir-tax*')
                                || request()->routeIs('company.natgov*')
                                || request()->routeIs('company.corporate-formation*');

                            $companyMarketingOpen =
                                request()->routeIs('company.products*')
                                || request()->routeIs('company.services.*');

                            $companySalesOpen = request()->routeIs('company.deals*');
                            $companyAccountsOpen = request()->routeIs('company.contacts*');

                            $companyOperationsOpen =
                                request()->routeIs('company.activities*')
                                || request()->routeIs('company.regular')
                                || request()->routeIs('company.projects');
                        @endphp

                        @if(! $hasCompanyContext)
                            <div class="px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg">
                                Company context unavailable for sidebar links.
                            </div>
                        @endif

                        @if($hasCompanyContext)
                            <div class="space-y-1">

                                {{-- TOP FIXED COMPANY ITEMS --}}
                                <a href="{{ route('company.kyc', ['company' => $currentCompanyId, 'tab' => 'business-client-information']) }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('company.kyc') || request()->routeIs('company.bif.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    KYC
                                </a>

                                <a href="{{ route('company.history', $currentCompanyId) }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('company.history') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    History
                                </a>

                                <a href="{{ route('company.consultation-notes', $currentCompanyId) }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('company.consultation-notes*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Consultation Notes
                                </a>

                                <div class="my-3 border-t border-gray-100"></div>

                                {{-- CORPORATE --}}
                                <div x-data="{ open: {{ $companyCorporateOpen ? 'true' : 'false' }} }" class="space-y-1">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                        {{ $companyCorporateOpen ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent hover:bg-gray-100 text-gray-700' }}">
                                        <span>Corporate</span>
                                        <i class="fas fa-chevron-down text-[11px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                        <a href="{{ route('company.corporate-formation', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.corporate-formation*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Corporate Formation
                                        </a>

                                        <a href="{{ route('company.bir-tax', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.bir-tax*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            BIR & Tax
                                        </a>

                                        <a href="{{ route('company.natgov', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.natgov*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            NatGov
                                        </a>

                                        <a href="{{ route('company.lgu', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.lgu*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            LGU
                                        </a>

                                        <a href="{{ route('company.accounting', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.accounting*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Accounting
                                        </a>

                                        <a href="{{ route('company.banking', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.banking*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Banking
                                        </a>

                                        <a href="{{ route('company.legal', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.legal*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Legal
                                        </a>

                                        <a href="{{ route('company.operations', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.operations*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Operations
                                        </a>

                                        <a href="{{ route('company.correspondence', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.correspondence*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Correspondence
                                        </a>
                                    </div>
                                </div>

                                {{-- MARKETING --}}
                                <div x-data="{ open: {{ $companyMarketingOpen ? 'true' : 'false' }} }" class="space-y-1">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                        {{ $companyMarketingOpen ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent hover:bg-gray-100 text-gray-700' }}">
                                        <span>Marketing</span>
                                        <i class="fas fa-chevron-down text-[11px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                        <a href="{{ route('company.products', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.products*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Product
                                        </a>

                                        <a href="{{ route('company.services.index', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.services.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Services
                                        </a>
                                    </div>
                                </div>

                                {{-- SALES --}}
                                <div x-data="{ open: {{ $companySalesOpen ? 'true' : 'false' }} }" class="space-y-1">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                        {{ $companySalesOpen ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent hover:bg-gray-100 text-gray-700' }}">
                                        <span>Sales</span>
                                        <i class="fas fa-chevron-down text-[11px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                        <a href="{{ route('company.deals', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.deals*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Deals
                                        </a>
                                    </div>
                                </div>

                                {{-- ACCOUNTS --}}
                                <div x-data="{ open: {{ $companyAccountsOpen ? 'true' : 'false' }} }" class="space-y-1">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                        {{ $companyAccountsOpen ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent hover:bg-gray-100 text-gray-700' }}">
                                        <span>Accounts</span>
                                        <i class="fas fa-chevron-down text-[11px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                        <a href="{{ route('company.contacts', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.contacts*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Contact
                                        </a>
                                    </div>
                                </div>

                                {{-- OPERATIONS --}}
                                <div x-data="{ open: {{ $companyOperationsOpen ? 'true' : 'false' }} }" class="space-y-1">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                        {{ $companyOperationsOpen ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent hover:bg-gray-100 text-gray-700' }}">
                                        <span>Operations</span>
                                        <i class="fas fa-chevron-down text-[11px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                        <a href="{{ route('company.activities', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.activities*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Activities
                                        </a>

                                        <a href="{{ route('company.regular', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.regular') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Regular
                                        </a>

                                        <a href="{{ route('company.projects', $currentCompanyId) }}"
                                           class="block px-3 py-2 rounded-lg transition {{ request()->routeIs('company.projects') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                            Project
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>

        @elseif(
            Auth::user()->hasPermission('access_corporate')
            && (
                request()->routeIs('corporate*')
                || request()->routeIs('stock-transfer-book*')
                || request()->routeIs('bir-tax*')
                || request()->routeIs('natgov*')
                || request()->routeIs('notices*')
                || request()->routeIs('minutes*')
                || request()->routeIs('resolutions*')
                || request()->routeIs('secretary-certificates*')
                || request()->routeIs('accounting')
                || request()->routeIs('banking')
                || request()->routeIs('legal')
                || request()->routeIs('operations')
                || request()->routeIs('correspondence*')
            )
        )
            <aside x-data="{ scrollCorporateNav(amount) { this.$refs.corporateNav?.scrollBy({ top: amount, behavior: 'smooth' }); } }"
                   class="w-72 bg-white border-r border-gray-200 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Corporate</p>
                </div>

                <div x-ref="corporateNav" class="flex-1 overflow-y-auto p-3 no-scrollbar">
                    <div class="space-y-1 text-sm">
                        <a href="{{ route('corporate') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Company General Information
                        </a>

                        <a href="{{ route('corporate.formation') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate.formation') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Corporate Formation
                        </a>

                        <div x-data="{ open: {{ request()->routeIs('stock-transfer-book*') ? 'true' : 'false' }} }" class="space-y-1">
                            <button type="button"
                                    @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition border
                                    {{ request()->routeIs('stock-transfer-book*') ? 'bg-blue-50 text-blue-700 border-blue-100 font-semibold' : 'border-transparent hover:bg-gray-100 text-gray-700' }}">
                                <span>Stock and Transfer Book</span>
                                <i class="fas fa-chevron-down text-[11px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>

                            <div x-cloak x-show="open" x-transition class="pl-3 space-y-1">
                                <a href="{{ route('stock-transfer-book.index') }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('stock-transfer-book.index*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Index
                                </a>
                                <a href="{{ route('stock-transfer-book.journal') }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('stock-transfer-book.journal*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Journal
                                </a>
                                <a href="{{ route('stock-transfer-book.ledger') }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('stock-transfer-book.ledger*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Ledger
                                </a>
                                <a href="{{ route('stock-transfer-book.installment') }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('stock-transfer-book.installment*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Installment
                                </a>
                                <a href="{{ route('stock-transfer-book.certificates') }}"
                                   class="block px-3 py-2 rounded-lg transition
                                   {{ request()->routeIs('stock-transfer-book.certificates*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                                    Certificates
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('bir-tax') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('bir-tax*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            BIR & Tax
                        </a>

                        <a href="{{ route('natgov') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('natgov*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            NatGov
                        </a>

                        <a href="{{ route('corporate.lgu') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate.lgu') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            LGU
                        </a>

                        <a href="{{ route('corporate.accounting') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate.accounting') || request()->routeIs('corporate.accounting.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Accounting
                        </a>

                        <a href="{{ route('corporate.banking') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate.banking') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Banking
                        </a>

                        <a href="{{ route('corporate.legal') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate.legal') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Legal
                        </a>

                        <a href="{{ route('corporate.operations') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('corporate.operations') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Operations
                        </a>

                        <a href="{{ route('correspondence') }}"
                           class="block px-3 py-2 rounded-lg transition
                           {{ request()->routeIs('correspondence*') ? 'bg-blue-50 text-blue-700 border border-blue-100 font-semibold' : 'hover:bg-gray-100 text-gray-700' }}">
                            Correspondence
                        </a>
                    </div>
                </div>
            </aside>
        @endif

        <!-- MAIN CONTENT -->
        <main class="flex-1 min-w-0 overflow-y-auto">
            @yield('content')
        </main>

    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    @stack('scripts')
</body>
</html>
