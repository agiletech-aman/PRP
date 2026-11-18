<x-layouts.form title="Sign in" :appraisal="$appraisal">
    <div class="auth-card form-login-card">
        <span class="badge badge-blue">{{ $appraisal->title }}</span>
        <h2>Sign in to open your appraisal form</h2>
        <p class="muted">Use the Employee ID (or email) and password from your appraisal mail. The form shows the part that is yours to fill in.</p>

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            <div class="field">
                <label for="login">Employee ID or email</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" placeholder="e.g. EMP1024"
                       autocomplete="username" required autofocus @class(['is-invalid' => $errors->has('login')])>
                @error('login')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="Password from your mail"
                       autocomplete="current-password" required @class(['is-invalid' => $errors->has('password')])>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign in &amp; open form</button>
        </form>

        <p class="auth-help">Lost your password? Ask HR to send your login details again.</p>
    </div>
</x-layouts.form>
