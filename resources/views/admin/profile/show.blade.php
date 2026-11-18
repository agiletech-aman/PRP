<x-layouts.admin title="My profile" subtitle="Your details, team and account settings.">
    <div class="profile-layout">
        <aside class="profile-side">
            <section class="panel profile-card">
                <span class="avatar avatar-lg">{{ $user->initials() }}</span>
                <h2>{{ $user->name }}</h2>
                <p class="muted">{{ $user->designation ?: 'No designation' }}</p>
                <div class="profile-tags">
                    @if ($user->role)<span class="pill pill-blue">{{ $user->role->name }}</span>@endif
                    <span @class(['pill', 'pill-green' => $user->is_active, 'pill-gray' => ! $user->is_active])>{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                </div>

                <dl class="detail-list">
                    <div><dt>Employee ID</dt><dd><span class="code-chip">{{ $user->employee_code ?? '—' }}</span></dd></div>
                    <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                    <div><dt>Department</dt><dd>{{ $user->department?->name ?? '—' }}</dd></div>
                    <div><dt>Reporting manager</dt><dd>{{ $user->manager?->name ?? '—' }}</dd></div>
                    <div><dt>Joining date</dt><dd>{{ $user->joining_date?->format('d M Y') ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="panel">
                <h2 class="panel-title">What you can do</h2>
                @if ($permissionLabels === [])
                    <p class="muted small">Your role has no permissions yet.</p>
                @else
                    <ul class="check-list">
                        @foreach ($permissionLabels as $label)
                            <li>{{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>

        <div class="profile-main">
            <x-form-card :action="route('admin.profile.update')" method="PUT" submit="Save profile">
                <x-form-section title="Edit profile" description="Department, role and manager are managed by HR.">
                    <x-field name="name" label="Full name" required>
                        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    </x-field>
                    <x-field name="email" label="Email" required>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    </x-field>
                </x-form-section>
            </x-form-card>

            <x-form-card :action="route('admin.profile.password')" method="PUT" submit="Change password">
                <x-form-section title="Change password" description="Use at least 8 characters. You stay signed in after changing it.">
                    <x-field name="current_password" label="Current password" bag="updatePassword" required full>
                        <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
                    </x-field>
                    <x-field name="password" label="New password" bag="updatePassword" required>
                        <input id="password" type="password" name="password" autocomplete="new-password" required>
                    </x-field>
                    <x-field name="password_confirmation" label="Confirm new password" bag="updatePassword" required>
                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                    </x-field>
                </x-form-section>
            </x-form-card>

            <section class="panel">
                <h2 class="panel-title">My team <span class="muted small">({{ $user->directReports->count() }})</span></h2>
                @if ($user->directReports->isEmpty())
                    <p class="muted small">Nobody reports to you.</p>
                @else
                    <ul class="team-list">
                        @foreach ($user->directReports as $member)
                            <li>
                                <span class="avatar avatar-sm">{{ $member->initials() }}</span>
                                <span>
                                    <strong>{{ $member->name }}</strong>
                                    <span class="muted small">{{ $member->designation ?: $member->employee_code }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-layouts.admin>
