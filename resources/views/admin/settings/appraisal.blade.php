<x-layouts.admin title="Settings" subtitle="Configure report recipients and your portal theme">
    <a href="{{ route('admin.roles.index') }}" class="back-link">Roles &amp; Permissions</a>

    <form method="POST" action="{{ route('admin.settings.appraisal.update') }}">
        @csrf
        @method('PUT')

        <div class="card card-narrow">
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

        <div class="card card-narrow">
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
