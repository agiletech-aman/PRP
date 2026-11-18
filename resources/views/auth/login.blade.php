<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="auth">
    <section class="auth-hero">
        <div class="brand brand-light">
            <span class="brand-mark brand-mark-logo">
                <img src="{{ asset('logo-mark.png') }}" alt="" width="128" height="135">
            </span>
            {{ config('app.name') }}
        </div>

        <h1>Performance reviews, in one place.</h1>
        <p>Complete your self-assessment, track manager feedback and view your final rating for the appraisal cycle.</p>

        <svg class="auth-illustration" viewBox="0 0 340 220" role="img" aria-label="Appraisal dashboard illustration">
            <rect x="0" y="0" width="340" height="220" rx="12" fill="#fff"/>
            <path d="M12 0h316a12 12 0 0 1 12 12v28H0V12A12 12 0 0 1 12 0z" fill="#eef0f3"/>
            <circle cx="22" cy="20" r="5" fill="#c9ced6"/><circle cx="38" cy="20" r="5" fill="#c9ced6"/><circle cx="54" cy="20" r="5" fill="#c9ced6"/>
            <circle cx="42" cy="91" r="25" fill="#d0121b"/>
            <circle cx="42" cy="84" r="8" fill="#fff"/><path d="M28 104a14 11 0 0 1 28 0z" fill="#fff"/>
            <rect x="80" y="76" width="100" height="8" rx="4" fill="#1e2026"/>
            <rect x="80" y="93" width="70" height="6" rx="3" fill="#d6dae0"/>
            <path d="M232 73l5.3 10.7 11.7 1.7-8.5 8.3 2 11.7-10.5-5.5-10.5 5.5 2-11.7-8.5-8.3 11.7-1.7z" fill="#f2b84b"/>
            <path d="M269 73l5.3 10.7 11.7 1.7-8.5 8.3 2 11.7-10.5-5.5-10.5 5.5 2-11.7-8.5-8.3 11.7-1.7z" fill="#f2b84b"/>
            <path d="M306 73l5.3 10.7 11.7 1.7-8.5 8.3 2 11.7-10.5-5.5-10.5 5.5 2-11.7-8.5-8.3 11.7-1.7z" fill="#eceef1"/>
            <line x1="20" y1="130" x2="312" y2="130" stroke="#e6e9ed" stroke-width="2"/>
            <rect x="30" y="170" width="26" height="33" rx="2" fill="#d6dae0"/>
            <rect x="70" y="156" width="26" height="47" rx="2" fill="#d6dae0"/>
            <rect x="110" y="147" width="26" height="56" rx="2" fill="#d0121b"/>
            <rect x="150" y="161" width="26" height="42" rx="2" fill="#d6dae0"/>
            <circle cx="254" cy="169" r="28" fill="none" stroke="#eceef1" stroke-width="10"/>
            <circle cx="254" cy="169" r="28" fill="none" stroke="#d0121b" stroke-width="10" stroke-dasharray="140 176" stroke-linecap="round" transform="rotate(-90 254 169)"/>
        </svg>
    </section>

    <main class="auth-panel">
        <div class="auth-card">
            <h2>Sign in</h2>
            <p class="muted">Use your employee credentials to continue.</p>

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="login">Employee ID or email</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" placeholder="e.g. EMP1024"
                           autocomplete="username" required autofocus @class(['is-invalid' => $errors->has('login')])>
                    @error('login')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <div class="field-row">
                        <label for="password">Password</label>
                        <a href="mailto:hr@example.com?subject=Password%20reset" class="link">Forgot password?</a>
                    </div>
                    <input id="password" name="password" type="password" placeholder="Enter your password"
                           autocomplete="current-password" required @class(['is-invalid' => $errors->has('password')])>
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Keep me signed in
                </label>

                <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            </form>

            <p class="auth-help">Trouble signing in? <a href="mailto:hr@example.com" class="link">Contact HR</a></p>
        </div>
    </main>
</body>
</html>
