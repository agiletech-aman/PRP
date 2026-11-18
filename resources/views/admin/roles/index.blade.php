<x-layouts.admin title="Roles & Permissions" subtitle="Create roles, choose what each can do, then assign them from the Employees page.">
    <div class="toolbar toolbar-end">
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add role</a>
    </div>

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Role</th><th>Permissions</th><th>Employees</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>
                            <strong>{{ $role->name }}</strong>
                            @if ($role->is_system)<span class="pill pill-gray">System</span>@endif
                            @if ($role->description)<div class="muted small">{{ $role->description }}</div>@endif
                        </td>
                        <td>
                            @if ($role->isSuperAdmin())
                                <span class="pill pill-blue">Full access</span>
                            @else
                                {{ $role->permissions_count }} of {{ $permissionCount }}
                            @endif
                        </td>
                        <td>{{ $role->users_count }}</td>
                        <td>
                            <div class="actions">
                                <x-icon-link :href="route('admin.employees.index', ['role' => $role->id])" icon="users" label="View employees" />
                                @unless ($role->isSuperAdmin())
                                    <x-icon-link :href="route('admin.roles.edit', $role)" icon="edit" label="Edit" />
                                @endunless
                                @unless ($role->is_system)
                                    <x-icon-delete :action="route('admin.roles.destroy', $role)" :confirm="'Delete the '.$role->name.' role?'" />
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
