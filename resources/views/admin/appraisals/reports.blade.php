<x-layouts.admin title="Performance Reports" subtitle="View employee ratings and export appraisal data">
    <div class="toolbar">
        <form method="GET" action="{{ route('admin.appraisal-reports.index') }}" class="inline-form filters">
            <div class="search-input">
                <x-icon name="search" />
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search employee name or code" aria-label="Search employee performance">
            </div>
            <select name="department" onchange="this.form.submit()" aria-label="Filter by department">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) request('department') === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @if (request()->hasAny(['search', 'department']))
                <a href="{{ route('admin.appraisal-reports.index') }}" class="btn btn-ghost">Clear</a>
            @endif
        </form>
        <a href="{{ route('admin.appraisal-reports.export', request()->query()) }}" class="btn btn-primary">Download Excel CSV</a>
    </div>

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th><th>Department</th><th>Manager</th><th>Self rating</th>
                    <th>Manager rating</th><th>HR rating</th><th>Status</th><th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appraisals as $appraisal)
                    <tr>
                        <td>
                            <strong>{{ $appraisal->employee->name }}</strong>
                            <div class="muted small">{{ $appraisal->employee->employee_code }}</div>
                        </td>
                        <td>{{ $appraisal->department?->name ?? $appraisal->employee->department?->name ?? '—' }}</td>
                        <td>{{ $appraisal->manager?->name ?? '—' }}</td>
                        <td>{{ $appraisal->averageRating('employee') ?? '—' }}</td>
                        <td>{{ $appraisal->averageRating('manager') ?? '—' }}</td>
                        <td>{{ $appraisal->averageRating('hr') ?? '—' }}</td>
                        <td><span class="pill {{ $appraisal->status->pillClass() }}">{{ $appraisal->status->label() }}</span></td>
                        <td>
                            <div class="actions">
                                <x-icon-link :href="route('appraisal.form', $appraisal)" icon="eye" label="View" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No appraisal reports found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($appraisals->hasPages())
            <div class="pager">{{ $appraisals->links('pagination::simple-bootstrap-5') }}</div>
        @endif
    </div>
</x-layouts.admin>
