<x-form-section title="Role details" description="A clear name helps HR pick the right role.">
    <x-field name="name" label="Role name" required>
        <input id="name" type="text" name="name" value="{{ old('name', $role->name) }}" placeholder="e.g. Team Lead" required>
    </x-field>
    <x-field name="description" label="Description">
        <input id="description" type="text" name="description" value="{{ old('description', $role->description) }}" placeholder="What is this role for?">
    </x-field>
</x-form-section>

<x-form-section title="Permissions" description="Tick everything people with this role are allowed to do." class="form-section-body-single">
    @error('permissions')<p class="field-error">{{ $message }}</p>@enderror
    <div class="permission-groups">
        @foreach ($permissionGroups as $group => $permissions)
            <fieldset class="permission-group">
                <legend>{{ $group }}</legend>
                @foreach ($permissions as $permission)
                    <label class="checkbox">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                               @checked(in_array($permission->id, array_map('intval', $selectedPermissionIds), true))>
                        <span>{{ $permission->label }}</span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
    </div>
</x-form-section>
