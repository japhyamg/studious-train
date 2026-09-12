<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — MoniSurv</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/monisurv.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('assets/logo.png') }}" alt="MoniSurv">
            <div>
                <div class="sidebar-brand-text">MoniSurv</div>
                <div class="sidebar-brand-sub">Surveillance · Monitoring</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            {{-- Workspace --}}
            <div class="sidebar-section">Workspace</div>
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            @can('audit-trail')<a href="{{ route('audit-trail.index') }}" class="sidebar-link {{ request()->routeIs('audit-trail.*') ? 'active' : '' }}"><i class="bi bi-journal-text"></i> Audit Trail</a>@endcan

            {{-- Investigation --}}
            @canany(['case-list','case-create','case-carrd','case-performance','case-false-positive-dashboard'])
            <div class="sidebar-section">Investigation</div>
            @can('case-list')<a href="{{ route('case-management.index') }}" class="sidebar-link {{ request()->routeIs('case-management.index') || request()->routeIs('case-management.show') ? 'active' : '' }}"><i class="bi bi-folder2-open"></i> Case Management</a>@endcan
            @can('case-carrd')<a href="{{ route('case-management.carrd') }}" class="sidebar-link {{ request()->routeIs('case-management.carrd') ? 'active' : '' }}"><i class="bi bi-clock-history"></i> C.A.R.D</a>@endcan
            @can('case-performance')<a href="{{ route('case-management.performance') }}" class="sidebar-link {{ request()->routeIs('case-management.performance') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Review Performance</a>@endcan
            @can('case-false-positive-dashboard')<a href="{{ route('case-management.false-positive-dashboard') }}" class="sidebar-link {{ request()->routeIs('case-management.false-positive*') ? 'active' : '' }}"><i class="bi bi-pie-chart"></i> False Positives</a>@endcan
            @can('mi-reports')<a href="{{ route('mi-reports.index') }}" class="sidebar-link {{ request()->routeIs('mi-reports.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-bar-graph"></i> MI Reports</a>@endcan
            @endcan

            {{-- Monitoring --}}
            @canany(['transaction-list'])
            <div class="sidebar-section">Monitoring</div>
            <a href="{{ route('transactions.index') }}" class="sidebar-link {{ request()->routeIs('transactions.index') || request()->routeIs('transactions.detail') ? 'active' : '' }}"><i class="bi bi-arrow-left-right"></i> Transactions</a>
            @canany(['rule-list','rule-create','rule-edit-value'])
            <a href="{{ route('transaction-rules.index') }}" class="sidebar-link {{ request()->routeIs('transaction-rules.*') ? 'active' : '' }}"><i class="bi bi-shield-check"></i> Transaction Rules</a>
            @endcan
            <a href="{{ route('transactions.risk-scoring-config') }}" class="sidebar-link {{ request()->routeIs('transactions.risk-scoring-config*') ? 'active' : '' }}"><i class="bi bi-sliders"></i> Risk Scoring</a>
            <a href="{{ route('ai-alerts.index') }}" class="sidebar-link {{ request()->routeIs('ai-alerts.*') ? 'active' : '' }}"><i class="bi bi-cpu"></i> AI Alerts</a>
            @endcan

            {{-- Customers --}}
            @canany(['customer-list','customer-create','risk-rating-list','internal-watchlist-list','nibss-watchlist-list'])
            <div class="sidebar-section">Customers</div>
            @canany(['customer-list','customer-create'])
            <a href="{{ route('customers.all') }}" class="sidebar-link {{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="bi bi-people"></i> Customer List</a>
            @endcan
            @canany(['risk-rating-list','risk-rating-generate','risk-profile-list','risk-level-list'])
            <a href="{{ route('risk-rating.index') }}" class="sidebar-link {{ request()->routeIs('risk-rating.*') ? 'active' : '' }}"><i class="bi bi-graph-up-arrow"></i> Customer Risk Rating</a>
            @endcan
            <a href="{{ route('peer-grouping.index') }}" class="sidebar-link {{ request()->routeIs('peer-grouping.*') ? 'active' : '' }}"><i class="bi bi-diagram-3"></i> Peer Groups</a>
            <a href="{{ route('pas.index') }}" class="sidebar-link {{ request()->routeIs('pas.*') ? 'active' : '' }}"><i class="bi bi-search"></i> P.A.S Screening</a>
            @canany(['internal-watchlist-list','nibss-watchlist-list'])
            <div class="sidebar-link sidebar-parent {{ request()->routeIs('watch-list.*') ? 'open' : '' }}"><i class="bi bi-exclamation-diamond"></i> Watchlists</div>
            <div class="sidebar-submenu {{ request()->routeIs('watch-list.*') ? 'show' : '' }}">
                <a href="{{ route('watch-list.internal.all') }}" class="sidebar-link {{ request()->routeIs('watch-list.internal.*') ? 'active' : '' }}">Internal</a>
                <a href="{{ route('watch-list.nibss.all') }}" class="sidebar-link {{ request()->routeIs('watch-list.nibss.*') ? 'active' : '' }}">NIBSS</a>
            </div>
            @endcan
            @endcan

            {{-- Administration --}}
            @canany(['team-list','team-create','manage-roles','assign-rules'])
            <div class="sidebar-section">Administration</div>
            <div class="sidebar-link sidebar-parent {{ request()->routeIs('manage-team.*') ? 'open' : '' }}"><i class="bi bi-person-gear"></i> Team</div>
            <div class="sidebar-submenu {{ request()->routeIs('manage-team.*') ? 'show' : '' }}">
                <a href="{{ route('manage-team.index') }}" class="sidebar-link {{ request()->routeIs('manage-team.index') || request()->routeIs('manage-team.show') || request()->routeIs('manage-team.add') ? 'active' : '' }}">Members</a>
                @can('manage-roles')<a href="{{ route('manage-team.manage-roles') }}" class="sidebar-link {{ request()->routeIs('manage-team.manage-roles') || request()->routeIs('manage-team.*role*') ? 'active' : '' }}">Roles & Permissions</a>@endcan
                @can('assign-rules')<a href="{{ route('manage-team.assign-rule.index') }}" class="sidebar-link {{ request()->routeIs('manage-team.assign-rule.*') ? 'active' : '' }}">Assign Rules</a>@endcan
            </div>
            @endcan

            <a href="{{ route('tools.view-validate-xml') }}" class="sidebar-link {{ request()->routeIs('tools.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-code"></i> XML Validator</a>
            @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('Super-Admin'))
            <a href="{{ route('cron-jobs.status') }}" class="sidebar-link {{ request()->routeIs('cron-jobs.*') ? 'active' : '' }}"><i class="bi bi-arrow-repeat"></i> Cron Jobs</a>
            <a href="{{ route('sanctions.index') }}" class="sidebar-link {{ request()->routeIs('sanctions.*') ? 'active' : '' }}"><i class="bi bi-globe2"></i> Sanction Lists</a>
            @endif

            {{-- Account --}}
            <div class="sidebar-section">Account</div>
            @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('Super-Admin'))
            <a href="{{ route('settings') }}" class="sidebar-link {{ request()->routeIs('settings') ? 'active' : '' }}"><i class="bi bi-gear"></i> System Settings</a>
            @endif
            <a href="{{ route('account-settings-profile') }}" class="sidebar-link {{ request()->routeIs('account-settings-profile') || request()->routeIs('account-settings-security') ? 'active' : '' }}"><i class="bi bi-person"></i> My Account</a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-footer-text">MoniSurv v1.0 · AML/CFT Platform</div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light d-lg-none" id="sidebarToggle" style="border-radius:10px;width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center"><i class="bi bi-list" style="font-size:18px"></i></button>

                {{-- Breadcrumb or Search --}}
                @hasSection('breadcrumb')
                    <nav class="breadcrumb-bar">@yield('breadcrumb')</nav>
                @else
                    <div class="header-search d-none d-md-flex">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Search transactions, cases..." readonly>
                        <kbd>/</kbd>
                    </div>
                @endif
            </div>
            <div class="d-flex align-items-center gap-3">
                {{-- Notifications --}}
                <div class="dropdown">
                    <button class="header-icon-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        @php $unreadCount = auth()->user()->unreadNotifications?->count() ?? 0; @endphp
                        @if($unreadCount > 0)
                        <span class="badge-notify">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                        @endif
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="width:320px;max-height:380px;overflow-y:auto">
                        <li class="px-3 py-2 border-bottom" style="font-size:13px;font-weight:700">Notifications</li>
                        @forelse(auth()->user()->notifications?->take(5) ?? [] as $notification)
                        <li>
                            <a class="dropdown-item d-flex gap-2 py-2" href="{{ $notification->data['url'] ?? '#' }}">
                                <i class="bi bi-flag-fill mt-1" style="font-size:11px;color:var(--green-600)"></i>
                                <div>
                                    <div style="font-size:12px;font-weight:500;line-height:1.3">{{ $notification->data['message'] ?? 'New notification' }}</div>
                                    <div style="font-size:10px;color:var(--text-muted)">{{ $notification->created_at?->diffForHumans() ?? '' }}</div>
                                </div>
                            </a>
                        </li>
                        @empty
                        <li class="px-3 py-4 text-center"><span style="font-size:12px;color:var(--text-muted)">No notifications</span></li>
                        @endforelse
                    </ul>
                </div>

                {{-- User Dropdown --}}
                <div class="dropdown">
                    <button class="btn btn-sm d-flex align-items-center gap-2 border-0 px-2 py-1" data-bs-toggle="dropdown" style="border-radius:10px">
                        <div class="avatar-circle" style="width:34px;height:34px;font-size:11px">{{ auth()->user()->initials ?? 'U' }}</div>
                        <div class="d-none d-md-block text-start">
                            <div style="font-size:12.5px;font-weight:600;line-height:1.2;color:var(--text-primary)">{{ auth()->user()->name ?? 'User' }}</div>
                            <div style="font-size:10px;color:var(--text-muted)">{{ auth()->user()->roles->first()?->name ?? '' }}</div>
                        </div>
                        <i class="bi bi-chevron-down" style="font-size:9px;color:var(--text-muted)"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:200px">
                        <li class="px-3 py-2 border-bottom">
                            <div style="font-size:12.5px;font-weight:600">{{ auth()->user()->name ?? 'User' }}</div>
                            <div style="font-size:11px;color:var(--text-muted)">{{ auth()->user()->email ?? '' }}</div>
                        </li>
                        <li><a class="dropdown-item" href="{{ route('account-settings-profile') }}"><i class="bi bi-person me-2" style="font-size:14px;opacity:.6"></i>My Account</a></li>
                        @can('audit-trail')<li><a class="dropdown-item" href="{{ route('audit-trail.index') }}"><i class="bi bi-journal-text me-2" style="font-size:14px;opacity:.6"></i>Activity Log</a></li>@endcan
                        <li><hr class="dropdown-divider"></li>
                        <li><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2" style="font-size:14px"></i>Sign Out</button></form></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="page-content">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;padding:16px"></button>
            </div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ session('error') }}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;padding:16px"></button>
            </div>
            @endif
            @if(isset($errors) && is_object($errors) && method_exists($errors, 'any') && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><ul class="mb-0 ps-3" style="font-size:12.5px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;padding:16px"></button>
            </div>
            @endif

            @yield('content')
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        // Sidebar toggle (mobile)
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('sidebarToggle');
        toggle?.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Sidebar collapsible menus
        document.querySelectorAll('.sidebar-parent').forEach(el => {
            el.addEventListener('click', () => {
                el.classList.toggle('open');
                const sub = el.nextElementSibling;
                if (sub?.classList.contains('sidebar-submenu')) {
                    sub.classList.toggle('show');
                }
            });
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-dismissible').forEach(el => {
                try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch (e) {}
            });
        }, 6000);

        // Keyboard shortcut for search (/)
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) {
                e.preventDefault();
                document.querySelector('.header-search input')?.focus();
            }
        });

        // Initialize tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
    </script>
    @stack('scripts')
</body>
</html>
