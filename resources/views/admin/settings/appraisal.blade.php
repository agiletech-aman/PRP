<x-layouts.admin title="Settings" subtitle="Choose who receives appraisal mail after the manager submits ratings">
    <a href="{{ route('admin.roles.index') }}" class="back-link">Roles &amp; Permissions</a>

    <form method="POST" action="{{ route('admin.settings.appraisal.update') }}">
        @csrf
        @method('PUT')

        <div class="card card-narrow">
            <div class="card-head">
                <div>
                    <h2>Post-manager mail recipients</h2>
                    <p class="muted small">Selected users receive an appraisal mail and remain HR evaluators.</p>
                </div>
            </div>

            @error('evaluator_ids')
                <div class="alert alert-error">{{ $message }}</div>
            @enderror

            <div class="permission-groups">
                @forelse ($eligibleUsers as $evaluator)
                    <label class="checkbox">
                        <input type="checkbox" name="evaluator_ids[]" value="{{ $evaluator->id }}"
                               @checked(in_array($evaluator->id, $selectedIds, true))>
                        <span>
                            <strong>{{ $evaluator->name }}</strong>
                            <span class="muted d-block">{{ $evaluator->employee_code }} · {{ $evaluator->email }}</span>
                        </span>
                    </label>
                @empty
                    <p class="muted">No active users have the HR evaluation permission or Admin role.</p>
                @endforelse
            </div>
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
