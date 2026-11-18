<x-layouts.admin title="Settings" subtitle="Configure email delivery, report recipients, and your portal theme">
    <a href="{{ route('admin.roles.index') }}" class="back-link">Roles &amp; Permissions</a>

    <form method="POST" action="{{ route('admin.settings.smtp.update') }}">
        @csrf

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>SMTP setup</h2>
                    <p class="muted small">Configure the server used to send portal emails, then send a test email to verify it.</p>
                </div>
            </div>

            @error('smtp')
                <div class="alert alert-error">{{ $message }}</div>
            @enderror

            <div class="field-grid">
            <x-field name="smtp_host" label="SMTP host" required>
                <input id="smtp_host" type="text" name="smtp_host" value="{{ old('smtp_host', $smtpSettings['host']) }}" required>
            </x-field>

            <x-field name="smtp_port" label="SMTP port" required>
                <input id="smtp_port" type="number" name="smtp_port" min="1" max="65535" value="{{ old('smtp_port', $smtpSettings['port']) }}" required>
            </x-field>

            <x-field name="smtp_encryption" label="Encryption" required>
                <select id="smtp_encryption" name="smtp_encryption" required>
                    <option value="tls" @selected(old('smtp_encryption', $smtpSettings['encryption']) === 'tls')>TLS (STARTTLS)</option>
                    <option value="ssl" @selected(old('smtp_encryption', $smtpSettings['encryption']) === 'ssl')>SSL/TLS</option>
                </select>
            </x-field>

            <x-field name="smtp_username" label="SMTP username">
                <input id="smtp_username" type="text" name="smtp_username" value="{{ old('smtp_username', $smtpSettings['username']) }}" autocomplete="username">
            </x-field>

            <x-field name="smtp_password" label="SMTP password" :hint="$smtpSettings['passwordConfigured'] ? 'A password is saved. Leave this blank to keep it unchanged.' : 'The password is encrypted before it is saved.'">
                <input id="smtp_password" type="password" name="smtp_password" autocomplete="new-password">
            </x-field>

            <x-field name="smtp_from_address" label="From email" required>
                <input id="smtp_from_address" type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $smtpSettings['fromAddress']) }}" required>
            </x-field>

            <x-field name="smtp_from_name" label="From name" required>
                <input id="smtp_from_name" type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $smtpSettings['fromName']) }}" required>
            </x-field>

            <x-field name="test_recipient" label="Test email recipient">
                <input id="test_recipient" type="email" name="test_recipient" value="{{ old('test_recipient') }}" autocomplete="email">
            </x-field>
            </div>
            <p class="field-hint">The test button checks the entered configuration without saving it.</p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" name="action" value="save">Save SMTP settings</button>
                <button type="submit" class="btn btn-outline" name="action" value="test">Send test email</button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.settings.appraisal.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>HR evaluation report mail recipients</h2>
                    <p class="muted small">When HR completes an evaluation, the appraisal report CSV will be mailed to these addresses.</p>
                </div>
            </div>

            <x-field name="report_recipient_emails" label="Email addresses">
                <textarea id="report_recipient_emails" name="report_recipient_emails" rows="4"
                          placeholder="hr@example.com, director@example.com">{{ old('report_recipient_emails', $reportRecipients) }}</textarea>
            </x-field>
            <p class="field-hint">Separate multiple addresses with commas or new lines.</p>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>Theme</h2>
                    <p class="muted small">Choose how the portal looks for your account.</p>
                </div>
            </div>

            <div class="theme-options">
                <label class="theme-option">
                    <input type="radio" name="theme_mode" value="light" @checked($themeMode === 'light')>
                    <span class="theme-preview theme-preview-light"><span></span><span></span></span>
                    <strong>Light</strong>
                </label>
                <label class="theme-option">
                    <input type="radio" name="theme_mode" value="dark" @checked($themeMode === 'dark')>
                    <span class="theme-preview theme-preview-dark"><span></span><span></span></span>
                    <strong>Dark</strong>
                </label>
            </div>

            <x-toggle name="sidebar_collapsed" label="Collapsed sidebar" hint="Show icons only in the left navigation." :checked="$sidebarCollapsed" />

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </div>
    </form>
</x-layouts.admin>
