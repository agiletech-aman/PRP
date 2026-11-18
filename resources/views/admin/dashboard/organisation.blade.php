@php
    $canViewEmployees = auth()->user()->can(\App\PermissionName::ViewEmployees->value);
    $canManageDepartments = auth()->user()->can(\App\PermissionName::ManageDepartments->value);
    $canManageRatings = auth()->user()->can(\App\PermissionName::ManageRatings->value);
    $inactiveCount = $employeeCount - $activeEmployeeCount;
@endphp

<x-layouts.admin
    title="Dashboard"
    :heading="$greeting.', '.\Illuminate\Support\Str::before(auth()->user()->name, ' ')"
    :subtitle="'Performance Review Portal · '.now()->format('l, d M Y')"
>
    <div class="stats">
        <x-stat label="Active employees" :value="$activeEmployeeCount.' / '.$employeeCount"
                :href="$canViewEmployees ? route('admin.employees.index', ['status' => 'active']) : null">
            <x-slot:hint>
                @if ($joinedLast30Days > 0)
                    <span class="trend trend-up">+{{ $joinedLast30Days }}</span> joined in last 30 days
                @else
                    {{ $inactiveCount }} inactive · no new joiners this month
                @endif
            </x-slot:hint>
        </x-stat>
        <x-stat label="Departments" :value="$departmentCount"
                :href="$canManageDepartments ? route('admin.departments.index') : null">
            <x-slot:hint>
                @if ($departmentsWithoutKra > 0)
                    <span class="trend trend-warn">{{ $departmentsWithoutKra }}</span> without KRA
                @else
                    All have a KRA heading
                @endif
            </x-slot:hint>
        </x-stat>
        <x-stat label="Rating headings" :value="$commonCategoryCount + $kraCategoryCount"
                :hint="$commonCategoryCount.' common · '.$kraCategoryCount.' department KRA'"
                :href="$canManageRatings ? route('admin.categories.index') : null" />
        <x-stat label="Rating factors" :value="$factorCount"
                :hint="($factorCount - $activeFactorCount) > 0 ? $activeFactorCount.' active · '.($factorCount - $activeFactorCount).' hidden' : 'All active'"
                :href="$canManageRatings ? route('admin.categories.index') : null" />
    </div>

    <div class="panels panels-wide-left">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Joining trend</h2>
                <span class="muted small">Last 12 months · {{ collect($joiningTrend)->sum('count') }} joined</span>
            </div>
            <div class="bar-chart" role="img" aria-label="Employees joined per month over the last 12 months">
                @foreach ($joiningTrend as $month)
                    <div class="bar-col" title="{{ $month['tooltip'] }}">
                        <span class="bar-count">{{ $month['count'] ?: '' }}</span>
                        <span class="bar" style="height: {{ max($month['height'], 2) }}%"></span>
                        <span class="bar-label">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Needs attention</h2>
            @if ($attentionItems === [])
                <div class="all-good">
                    <span class="all-good-icon" aria-hidden="true"></span>
                    <div>
                        <strong>All set</strong>
                        <span class="muted small d-block">Every employee has a department, manager and role.</span>
                    </div>
                </div>
            @else
                <ul class="attention-list">
                    @foreach ($attentionItems as $item)
                        <li>
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            @else
                                <span>{{ $item['label'] }}</span>
                            @endif
                            <span class="pill pill-warn">{{ $item['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="panels">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Employees per department</h2>
                <span class="muted small">Where people work</span>
            </div>
            <ul class="progress-list">
                @forelse ($departments as $department)
                    <li>
                        @if ($canViewEmployees)
                            <a href="{{ route('admin.employees.index', ['department' => $department->id]) }}" class="progress-label">{{ $department->name }}</a>
                        @else
                            <span class="progress-label">{{ $department->name }}</span>
                        @endif
                        <span class="progress-track"><span class="progress-bar" style="width: {{ round($department->users_count / $largestDepartmentSize * 100) }}%"></span></span>
                        <span class="progress-value">{{ $department->users_count }}</span>
                    </li>
                @empty
                    <li class="muted">No departments yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Employees per role</h2>
                <span class="muted small">Portal access level</span>
            </div>
            <ul class="progress-list">
                @forelse ($roles as $role)
                    @php($share = $employeeCount > 0 ? (int) round($role->users_count / $employeeCount * 100) : 0)
                    <li>
                        @if ($canViewEmployees)
                            <a href="{{ route('admin.employees.index', ['role' => $role->id]) }}" class="progress-label">{{ $role->name }}</a>
                        @else
                            <span class="progress-label">{{ $role->name }}</span>
                        @endif
                        <span class="progress-track"><span class="progress-bar" style="width: {{ $share }}%"></span></span>
                        <span class="progress-value" title="{{ $share }}% of employees">{{ $role->users_count }}</span>
                    </li>
                @empty
                    <li class="muted">No roles yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <h2 class="panel-title">Recently added</h2>
            <ul class="feed">
                @forelse ($recentEmployees as $employee)
                    <li class="feed-row">
                        <span class="avatar avatar-sm">{{ $employee->initials() }}</span>
                        <span class="feed-body">
                            <strong>{{ $employee->name }}</strong>
                            <span>{{ $employee->designation ?: 'No designation' }}@if ($employee->department) · {{ $employee->department->name }}@endif</span>
                        </span>
                        <time class="feed-time" datetime="{{ $employee->created_at?->toIso8601String() }}" title="{{ $employee->created_at?->format('d M Y, h:i A') }}">{{ $employee->created_at?->diffForHumans(short: true) }}</time>
                    </li>
                @empty
                    <li class="muted">No employees yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
