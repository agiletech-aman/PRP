<x-layouts.admin title="Appraisals" :subtitle="$appraisals->total().' '.\Illuminate\Support\Str::plural('appraisal', $appraisals->total()).' found'">
    <div class="toolbar">
        <form method="GET" action="{{ route('admin.appraisals.index') }}" class="inline-form filters">
            <div class="search-input">
                <x-icon name="search" />
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search employee name or code" aria-label="Search appraisals">
            </div>
            <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
                <option value="">Any status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if (request()->hasAny(['search', 'status']))
                <a href="{{ route('admin.appraisals.index') }}" class="btn btn-ghost">Clear</a>
            @endif
        </form>

        <a href="{{ route('admin.appraisals.create') }}" class="btn btn-primary"><x-icon name="plus" /> Send appraisal</a>
    </div>

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Employee</th><th>Appraisal</th><th>Manager</th><th>Sent</th><th>Self</th><th>Manager</th><th>HR</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($appraisals as $appraisal)
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar avatar-sm">{{ $appraisal->employee->initials() }}</span>
                                <div>
                                    <strong>{{ $appraisal->employee->name }}</strong>
                                    <div class="muted small">{{ $appraisal->department?->name ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $appraisal->title }}</td>
                        <td>{{ $appraisal->manager?->name ?? '—' }}</td>
                        <td>{{ $appraisal->sent_at?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $appraisal->employee_submitted_at?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $appraisal->manager_submitted_at?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $appraisal->hr_remarked_at?->format('d M Y') ?? '—' }}</td>
                        <td><span class="pill {{ $appraisal->status->pillClass() }}">{{ $appraisal->status->label() }}</span></td>
                        <td>
                            <div class="actions">
                                <x-icon-link :href="route('appraisal.form', $appraisal)" icon="eye" label="View" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No appraisals yet. Use “Send appraisal” to start one.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($appraisals->hasPages())
            <div class="pager">{{ $appraisals->links('pagination::simple-bootstrap-5') }}</div>
        @endif
    </div>
</x-layouts.admin>
