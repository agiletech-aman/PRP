<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\PermissionName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
            'permissionCount' => Permission::count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'role' => new Role,
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissionIds' => old('permissions', []),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'slug' => $this->uniqueSlug($request->validated('name')),
        ]);

        $role->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        abort_if($role->isSuperAdmin(), 403, 'The Admin role always has full access and cannot be edited.');

        return view('admin.roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissionIds' => old('permissions', $role->permissions()->pluck('permissions.id')->all()),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->isSuperAdmin(), 403, 'The Admin role always has full access and cannot be edited.');

        $role->update($request->safe()->only(['name', 'description']));
        $role->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => "Move the employees with the {$role->name} role to another role before deleting it."]);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    /**
     * @return \Illuminate\Support\Collection<string, Collection<int, Permission>>
     */
    private function permissionGroups(): \Illuminate\Support\Collection
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            );
        }

        return Permission::orderBy('id')->get()->groupBy('group');
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'role';
        $slug = $baseSlug;
        $suffix = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
