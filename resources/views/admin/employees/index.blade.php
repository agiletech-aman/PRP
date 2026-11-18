<x-layouts.admin title="Employees" :subtitle="$employees->total().' '.\Illuminate\Support\Str::plural('employee', $employees->total()).' found'">
    <div class="toolbar">
        <form method="GET" action="{{ route('admin.employees.index') }}" class="inline-form filters">
            <div class="search-input">
                <x-icon name="search" />
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, code or email" aria-label="Search employees">
            </div>
            <select name="department" onchange="this.form.submit()" aria-label="Filter by department">
                <option value="">All departments</option>
                <option value="none" @selected(request('department') === 'none')>No department</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <select name="role" onchange="this.form.submit()" aria-label="Filter by role">
                <option value="">All roles</option>
                <option value="none" @selected(request('role') === 'none')>No role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            @if ($managerFilterLabel)
                <input type="hidden" name="manager" value="{{ request('manager') }}">
                <a href="{{ route('admin.employees.index', request()->except(['manager', 'page'])) }}" class="filter-chip" title="Remove filter">
                    {{ $managerFilterLabel }} <span aria-hidden="true">×</span>
                </a>
            @endif
            @if (request()->hasAny(['search', 'department', 'role', 'manager', 'status']))
                <a href="{{ route('admin.employees.index') }}" class="btn btn-ghost">Clear</a>
            @endif
        </form>

        @can(\App\PermissionName::ManageEmployees->value)
            <div class="toolbar-actions">
                <a href="{{ route('admin.employees.import.sample') }}" class="btn btn-outline">Download sample CSV</a>
                <button type="button" class="btn btn-outline" id="open-employee-import">Import employees</button>
                <a href="{{ route('admin.employees.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add employee</a>
            </div>
        @endcan
    </div>

    @can(\App\PermissionName::ManageEmployees->value)
        <dialog class="confirmation-modal import-modal" id="employee-import-modal" aria-labelledby="employee-import-title">
            <form method="POST" action="{{ route('admin.employees.import') }}" enctype="multipart/form-data">
                @csrf
                <h2 id="employee-import-title">Import employees</h2>
                <p>Upload a CSV file with one employee per row. Use the downloaded sample format.</p>
                <div class="field">
                    <label for="employee-import-file">CSV file</label>
                    <input id="employee-import-file" type="file" name="file" accept=".csv,text/csv" required>
                    @error('file')
                        @foreach ((array) $errors->get('file') as $error)
                            <div class="field-error">{{ $error }}</div>
                        @endforeach
                    @enderror
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="cancel-employee-import">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import employees</button>
                </div>
            </form>
        </dialog>

        <script>
            const employeeImportModal = document.getElementById('employee-import-modal');
            const openEmployeeImport = document.getElementById('open-employee-import');
            const cancelEmployeeImport = document.getElementById('cancel-employee-import');

            if (employeeImportModal && openEmployeeImport && cancelEmployeeImport) {
                openEmployeeImport.addEventListener('click', () => employeeImportModal.showModal());
                cancelEmployeeImport.addEventListener('click', () => employeeImportModal.close());
            }

            @if ($errors->has('file'))
                employeeImportModal?.showModal();
            @endif
        </script>
    @endcan

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Employee</th><th>Code</th><th>Department</th><th>Role</th><th>Manager</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar avatar-sm">{{ $employee->initials() }}</span>
                                <div>
                                    <strong>{{ $employee->name }}</strong>
                                    <div class="muted small">{{ $employee->designation ?: $employee->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="code-chip">{{ $employee->employee_code }}</span></td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>
                            @if ($employee->role)
                                <span @class(['pill', 'pill-blue' => $employee->role->isSuperAdmin(), 'pill-gray' => ! $employee->role->isSuperAdmin()])>{{ $employee->role->name }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ $employee->manager?->name ?? '—' }}</td>
                        <td><span @class(['pill', 'pill-green' => $employee->is_active, 'pill-gray' => ! $employee->is_active])>{{ $employee->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="actions">
                                @can(\App\PermissionName::ManageEmployees->value)
                                    @if (! $employee->isSuperAdmin() || auth()->user()->isSuperAdmin())
                                        <x-icon-link :href="route('admin.employees.edit', $employee)" icon="edit" label="Edit" />
                                        @unless (auth()->user()->is($employee))
                                            <form method="POST" action="{{ route('admin.employees.send-credentials', $employee) }}" class="icon-form"
                                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from('Generate a new password for '.$employee->name.' and mail it to '.$employee->email.'? Their current password will stop working.') }})">
                                                @csrf
                                                <button type="submit" class="icon-btn" title="Send login credentials" aria-label="Send login credentials">
                                                    <x-icon name="mail" />
                                                </button>
                                            </form>
                                            <x-icon-delete :action="route('admin.employees.destroy', $employee)" :confirm="'Delete '.$employee->name.'?'" />
                                        @endunless
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No employees match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($employees->hasPages())
            <div class="pager">{{ $employees->links('pagination::simple-bootstrap-5') }}</div>
        @endif
    </div>
</x-layouts.admin>
