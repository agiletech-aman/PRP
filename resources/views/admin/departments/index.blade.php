<x-layouts.admin title="Departments" subtitle="Each department gets the common rating headings plus its own KRA.">
    <div class="card">
        <form method="POST" action="{{ route('admin.departments.store') }}" class="inline-form">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="New department name, e.g. Marketing" required>
            <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Add department</button>
        </form>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Department</th><th>Status</th><th>KRA headings</th><th>Employees</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td><strong>{{ $department->name }}</strong></td>
                        <td><span @class(['pill', 'pill-green' => $department->is_active, 'pill-gray' => ! $department->is_active])>{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ $department->rating_categories_count }}</td>
                        <td>{{ $department->users_count }}</td>
                        <td>
                            <div class="actions">
                                @can(\App\PermissionName::ManageRatings->value)
                                    <x-icon-link :href="route('admin.categories.index', ['department' => $department->id])" icon="target" label="Manage KRA" />
                                @endcan
                                <x-icon-link :href="route('admin.departments.form', $department)" icon="eye" label="Preview appraisal form" />
                                <x-icon-link :href="route('admin.departments.edit', $department)" icon="edit" label="Edit" />
                                <x-icon-delete :action="route('admin.departments.destroy', $department)" :confirm="'Delete '.$department->name.' and its KRA headings?'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No departments yet. Add your first one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
