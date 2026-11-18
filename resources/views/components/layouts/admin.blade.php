@props(['title' => 'Dashboard', 'heading' => null, 'subtitle' => null])

@php
    $currentUser = auth()->user();
    $themeMode = \App\Models\AppraisalSetting::get(\App\Http\Controllers\Admin\SettingsController::themeKey((int) $currentUser->id), 'light');
    $sidebarCollapsed = \App\Models\AppraisalSetting::get(\App\Http\Controllers\Admin\SettingsController::sidebarKey((int) $currentUser->id), '0') === '1';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body data-theme="{{ $themeMode === 'dark' ? 'dark' : 'light' }}" @class(['app', 'sidebar-collapsed' => $sidebarCollapsed])>
    <aside class="sidebar">
        <a href="{{ auth()->user()->homeUrl() }}" class="sidebar-logo">
            <img src="{{ asset('logo.png') }}" alt="Agile Enhance Technologies" width="480" height="148">
        </a>

        <nav class="nav">
            @can(\App\PermissionName::ViewDashboard->value)
                <a href="{{ route('admin.dashboard') }}" @class(['active' => request()->routeIs('admin.dashboard')])>
                    <x-icon name="chart" class="nav-icon" /> Dashboard
                </a>
            @endcan
            @can(\App\PermissionName::ViewEmployees->value)
                <a href="{{ route('admin.employees.index') }}" @class(['active' => request()->routeIs('admin.employees.*')])>
                    <x-icon name="users" class="nav-icon" /> Employees
                </a>
            @endcan
            @can(\App\PermissionName::ManageDepartments->value)
                <a href="{{ route('admin.departments.index') }}" @class(['active' => request()->routeIs('admin.departments.*')])>
                    <x-icon name="building" class="nav-icon" /> Departments
                </a>
            @endcan
            @can(\App\PermissionName::ManageRatings->value)
                <a href="{{ route('admin.categories.index') }}" @class(['active' => request()->routeIs('admin.categories.*', 'admin.factors.*')])>
                    <x-icon name="clipboard" class="nav-icon" /> KRA
                </a>
            @endcan
            @can(\App\PermissionName::SendAppraisals->value)
                <a href="{{ route('admin.appraisals.index') }}" @class(['active' => request()->routeIs('admin.appraisals.index', 'admin.appraisals.create')])>
                    <x-icon name="target" class="nav-icon" /> Appraisals
                </a>
            @endcan
            @can(\App\PermissionName::ViewPerformanceReports->value)
                <a href="{{ route('admin.appraisal-reports.index') }}" @class(['active' => request()->routeIs('admin.appraisal-reports.*')])>
                    <x-icon name="chart" class="nav-icon" /> Performance Reports
                </a>
            @endcan
            @if ($currentUser->hasAppraisals())
                <a href="{{ route('admin.appraisals.mine') }}" @class(['active' => request()->routeIs('admin.appraisals.mine')])>
                    <x-icon name="list" class="nav-icon" /> My Appraisals
                </a>
            @endif
            @can(\App\PermissionName::ManageRoles->value)
                <a href="{{ route('admin.roles.index') }}" @class(['active' => request()->routeIs('admin.roles.*')])>
                    <x-icon name="shield" class="nav-icon" /> Roles &amp; Permissions
                </a>
            @endcan
            @can(\App\PermissionName::ManageAppraisalSettings->value)
                <a href="{{ route('admin.settings.appraisal') }}" @class(['active' => request()->routeIs('admin.settings.appraisal')])>
                    <x-icon name="settings" class="nav-icon" /> Settings
                </a>
            @endcan
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="nav nav-bottom">
            @csrf
            <button type="submit"><x-icon name="logout" class="nav-icon" /> Sign out</button>
        </form>
    </aside>

    <div class="main">
        <header class="page-header">
            <div>
                <h1 class="page-title">{{ $heading ?? $title }}</h1>
                @if ($subtitle)
                    <p class="page-subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            <a href="{{ route('admin.profile.show') }}" @class(['user-chip', 'active' => request()->routeIs('admin.profile.*')]) title="My profile">
                <span class="avatar">{{ $currentUser->initials() }}</span>
                <span class="user-chip-text">
                    <strong>{{ $currentUser->name }}</strong>
                    <span>{{ $currentUser->designation ?: $currentUser->role?->name }}</span>
                </span>
            </a>
        </header>

        <main class="content">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->hasAny(['employee', 'role', 'employee_ids']))
                <div class="alert alert-error">
                    @foreach (array_merge($errors->get('employee'), $errors->get('role'), $errors->get('employee_ids')) as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @elseif ($errors->any())
                <div class="alert alert-error">Please fix the highlighted fields below.</div>
            @endif

            {{ $slot }}
        </main>
    </div>

    <script>
        /* Filter lists while typing: submit the search form shortly after the user stops typing. */
        document.querySelectorAll('.filters .search-input input[name="search"]').forEach((input) => {
            const focusKey = 'refocus-search:' + location.pathname;
            let timer;

            try {
                if (sessionStorage.getItem(focusKey)) {
                    sessionStorage.removeItem(focusKey);
                    input.focus();
                    input.setSelectionRange(input.value.length, input.value.length);
                }
            } catch (error) {}

            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    try { sessionStorage.setItem(focusKey, '1'); } catch (error) {}
                    input.form.requestSubmit();
                }, 500);
            });
        });
    </script>
</body>
</html>
