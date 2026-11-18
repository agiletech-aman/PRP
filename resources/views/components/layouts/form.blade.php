@props(['title', 'appraisal'])

@php
    $currentUser = auth()->user();
    $themeMode = $currentUser
        ? \App\Models\AppraisalSetting::get(\App\Http\Controllers\Admin\SettingsController::themeKey((int) $currentUser->id), 'light')
        : 'light';
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
<body class="form-page" data-theme="{{ $themeMode === 'dark' ? 'dark' : 'light' }}">
    <header class="form-topbar">
        <div class="form-topbar-inner">
            <img src="{{ asset('logo.png') }}" alt="Agile Enhance Technologies" class="form-topbar-logo" width="480" height="148">

            @if ($currentUser)
                <div class="form-topbar-user">
                    <span class="avatar avatar-sm">{{ $currentUser->initials() }}</span>
                    <span class="user-chip-text">
                        <strong>{{ $currentUser->name }}</strong>
                        <span>{{ $currentUser->employee_code }}</span>
                    </span>
                    <form method="POST" action="{{ route('appraisal.switch-user', $appraisal) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                    </form>
                </div>
            @endif
        </div>
    </header>

    <main class="form-page-main">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">Please fix the highlighted fields below.</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
