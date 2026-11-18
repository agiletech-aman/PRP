@php($canViewEmployees = $user->can(\App\PermissionName::ViewEmployees->value))

<x-layouts.admin
    title="Dashboard"
    :heading="$greeting.', '.\Illuminate\Support\Str::before($user->name, ' ')"
    :subtitle="'Your team overview'.($user->department ? ' · '.$user->department->name : '').' · '.now()->format('l, d M Y')"
>
    <div class="stats">
        <x-stat label="My team" :value="$teamSize"
                :hint="\Illuminate\Support\Str::plural('direct report', $teamSize)"
                :href="$canViewEmployees && $teamSize > 0 ? route('admin.employees.index', ['manager' => $user->id]) : null" />
        <x-stat label="Active members" :value="$activeTeamCount.' / '.$teamSize"
                :hint="($teamSize - $activeTeamCount) > 0 ? ($teamSize - $activeTeamCount).' inactive' : 'Everyone is active'" />
        <x-stat label="Average tenure" :value="$averageTenureYears !== null ? $averageTenureYears.' yrs' : '—'"
                :hint="$recentlyJoinedCount.' joined in last 12 months'" />
        <x-stat label="Headings on my form" :value="$formHeadingCount" :hint="$user->department?->name ?? 'Common headings only'" />
    </div>

    @if ($teamSize > 0)
        <section class="panel panel-spaced">
            <div class="panel-head">
                <h2 class="panel-title">Team joining trend</h2>
                <span class="muted small">Last 12 months · {{ collect($joiningTrend)->sum('count') }} joined</span>
            </div>
            <div class="bar-chart" role="img" aria-label="Team members joined per month over the last 12 months">
                @foreach ($joiningTrend as $month)
                    <div class="bar-col" title="{{ $month['tooltip'] }}">
                        <span class="bar-count">{{ $month['count'] ?: '' }}</span>
                        <span class="bar" style="height: {{ max($month['height'], 2) }}%"></span>
                        <span class="bar-label">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="panels">
        <section class="panel">
            <h2 class="panel-title">My team</h2>
            <ul class="status-list">
                @forelse ($team->take(8) as $member)
                    <li>
                        <span class="person">
                            <span class="avatar avatar-sm">{{ $member->initials() }}</span>
                            <span>
                                <strong>{{ $member->name }}</strong>
                                <span class="muted small d-block">{{ $member->designation ?: $member->employee_code }}</span>
                            </span>
                        </span>
                        <span @class(['pill', 'pill-green' => $member->is_active, 'pill-gray' => ! $member->is_active])>{{ $member->is_active ? 'Active' : 'Inactive' }}</span>
                    </li>
                @empty
                    <li class="muted">Nobody reports to you yet.</li>
                @endforelse
            </ul>
            @if ($team->count() > 8 && $canViewEmployees)
                <a href="{{ route('admin.employees.index', ['manager' => $user->id]) }}" class="panel-more">View all {{ $team->count() }} members</a>
            @endif
        </section>

        <section class="panel">
            <h2 class="panel-title">Team by designation</h2>
            <ul class="progress-list">
                @forelse ($designationBreakdown as $row)
                    <li>
                        <span class="progress-label" title="{{ $row['designation'] }}">{{ $row['designation'] }}</span>
                        <span class="progress-track"><span class="progress-bar" style="width: {{ $row['share'] }}%"></span></span>
                        <span class="progress-value">{{ $row['count'] }}</span>
                    </li>
                @empty
                    <li class="muted">No team members yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <h2 class="panel-title">About me</h2>
            <dl class="detail-list detail-list-plain">
                <div><dt>Role</dt><dd>{{ $user->role?->name ?? '—' }}</dd></div>
                <div><dt>Department</dt><dd>{{ $user->department?->name ?? '—' }}</dd></div>
                <div><dt>Reporting manager</dt><dd>{{ $user->manager?->name ?? '—' }}</dd></div>
                <div><dt>Joining date</dt><dd>{{ $user->joining_date?->format('d M Y') ?? '—' }}</dd></div>
            </dl>
            <a href="{{ route('admin.profile.show') }}" class="panel-more">Open my profile</a>
        </section>
    </div>
</x-layouts.admin>
