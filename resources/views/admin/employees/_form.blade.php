@php($isEditingSelf = $employee->exists && auth()->user()->is($employee))

<x-form-section title="Personal details" description="Basic information used to identify the employee.">
    <x-field name="employee_code" label="Employee ID" required>
        <input id="employee_code" type="text" name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" placeholder="e.g. EMP1024" maxlength="30" required>
    </x-field>
    <x-field name="name" label="Full name" required>
        <input id="name" type="text" name="name" value="{{ old('name', $employee->name) }}" placeholder="e.g. Neha Verma" required>
    </x-field>
    <x-field name="email" label="Email" required full>
        <input id="email" type="email" name="email" value="{{ old('email', $employee->email) }}" placeholder="name@company.com" required>
    </x-field>
</x-form-section>

<x-form-section title="Job details" description="Where they work and who reviews their appraisal.">
    <x-field name="designation" label="Designation">
        <input id="designation" type="text" name="designation" value="{{ old('designation', $employee->designation) }}" placeholder="e.g. Senior Developer">
    </x-field>
    <x-field name="department_id" label="Department" hint="Decides which KRA appears on their appraisal form.">
        <select id="department_id" name="department_id">
            <option value="">— None —</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $employee->department_id) === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field name="manager_id" label="Reporting manager" hint="This person gives the manager rating.">
        <select id="manager_id" name="manager_id">
            <option value="">— None —</option>
            @foreach ($managers as $manager)
                <option value="{{ $manager->id }}" @selected((string) old('manager_id', $employee->manager_id) === (string) $manager->id)>{{ $manager->name }} ({{ $manager->employee_code }})</option>
            @endforeach
        </select>
    </x-field>
    <x-field name="joining_date" label="Joining date">
        <input id="joining_date" type="date" name="joining_date" value="{{ old('joining_date', $employee->joining_date?->format('Y-m-d')) }}">
    </x-field>
</x-form-section>

<x-form-section title="Login & access" :description="$employee->exists ? 'Leave the password blank to keep the current one.' : 'They sign in with their Employee ID or email.'">
    <x-field name="role_id" label="Role" :required="! $isEditingSelf" :hint="$isEditingSelf ? 'You cannot change your own role.' : 'Controls what they can see and do.'" full>
        @if ($isEditingSelf)
            <input id="role_id" type="text" value="{{ $employee->role?->name ?? '—' }}" disabled>
        @else
            <select id="role_id" name="role_id" required>
                <option value="">Select a role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id', $employee->role_id) === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        @endif
    </x-field>
    <x-field name="password" label="Password" :required="! $employee->exists" hint="At least 8 characters.">
        <input id="password" type="password" name="password" autocomplete="new-password" @required(! $employee->exists)>
    </x-field>
    <x-field name="password_confirmation" label="Confirm password" :required="! $employee->exists">
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @required(! $employee->exists)>
    </x-field>

    @unless ($isEditingSelf)
        <div class="field-full">
            <x-toggle name="is_active" label="Active" hint="Inactive employees cannot sign in." :checked="old('is_active', $employee->is_active)" />
        </div>
    @endunless
</x-form-section>
