<x-layouts.admin title="Send appraisal" subtitle="The mail goes to each employee, with their reporting manager in CC">
    <a href="{{ route('admin.appraisals.index') }}" class="back-link"><x-icon name="arrow-left" /> All appraisals</a>

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.appraisals.create') }}" class="inline-form filters">
            <div class="search-input">
                <x-icon name="search" />
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, code or email" aria-label="Search employees">
            </div>
            <select name="department" onchange="this.form.submit()" aria-label="Filter by department">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @if (request()->hasAny(['search', 'department']))
                <a href="{{ route('admin.appraisals.create') }}" class="btn btn-ghost">Clear</a>
            @endif
        </form>
    </div>

    <form method="POST" action="{{ route('admin.appraisals.store') }}">
        @csrf

        <div class="card mb">
            <x-field name="title" label="Appraisal title" required hint="Shown in the mail subject and on the form, e.g. “Annual Appraisal 2026”.">
                <input id="title" type="text" name="title" value="{{ old('title', $defaultTitle) }}" required maxlength="255">
            </x-field>
        </div>

        <div class="card card-flush">
            <table class="table">
                <thead>
                    <tr>
                        <th class="select-col">
                            <input type="checkbox" id="select-all" aria-label="Select all eligible employees">
                        </th>
                        <th>Employee</th><th>Department</th><th>Reporting manager (CC)</th><th>Can send?</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php
                            $blockedReason = match (true) {
                                $employee->department === null => 'No department',
                                $employee->manager === null => 'No reporting manager',
                                ! $employee->manager->is_active => 'Manager is inactive',
                                (bool) $employee->has_open_appraisal => 'Appraisal in progress',
                                default => null,
                            };
                        @endphp
                        <tr>
                            <td class="select-col">
                                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="employee-check"
                                       aria-label="Select {{ $employee->name }}"
                                       @checked(in_array($employee->id, array_map('intval', old('employee_ids', [])), true))
                                       @disabled($blockedReason !== null)>
                            </td>
                            <td>
                                <div class="person">
                                    <span class="avatar avatar-sm">{{ $employee->initials() }}</span>
                                    <div>
                                        <strong>{{ $employee->name }}</strong>
                                        <div class="muted small">{{ $employee->employee_code }} · {{ $employee->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td>
                                @if ($employee->manager)
                                    {{ $employee->manager->name }}
                                    <div class="muted small">{{ $employee->manager->email }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($blockedReason)
                                    <span class="pill pill-gray">{{ $blockedReason }}</span>
                                @else
                                    <span class="pill pill-green">Ready</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No active employees match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="form-actions sticky-actions">
            <span class="muted" id="selected-count">0 selected</span>
            <button type="submit" class="btn btn-primary">Send appraisal mail</button>
        </div>
    </form>

    <script>
        (() => {
            const selectAll = document.getElementById('select-all');
            const counter = document.getElementById('selected-count');
            const checks = () => [...document.querySelectorAll('.employee-check:not(:disabled)')];
            const refresh = () => {
                const selected = checks().filter((box) => box.checked).length;
                counter.textContent = `${selected} selected`;
                selectAll.checked = selected > 0 && selected === checks().length;
            };

            selectAll.addEventListener('change', () => {
                checks().forEach((box) => { box.checked = selectAll.checked; });
                refresh();
            });
            checks().forEach((box) => box.addEventListener('change', refresh));
            refresh();
        })();
    </script>
</x-layouts.admin>
