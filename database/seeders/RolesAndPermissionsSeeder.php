<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\PermissionName;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Default roles and the permissions each one starts with. Admin always has every permission.
     *
     * @var array<string, array{name: string, description: string, is_system: bool, permissions: list<PermissionName>}>
     */
    private const ROLES = [
        Role::SUPER_ADMIN_SLUG => [
            'name' => 'Admin',
            'description' => 'Full access to everything.',
            'is_system' => true,
            'permissions' => [],
        ],
        'hr' => [
            'name' => 'HR',
            'description' => 'Manages employees, departments and the appraisal structure.',
            'is_system' => false,
            'permissions' => [
                PermissionName::ViewDashboard, PermissionName::ViewEmployees, PermissionName::ManageEmployees,
                PermissionName::ManageDepartments, PermissionName::ManageRatings, PermissionName::SendAppraisals,
            ],
        ],
        'manager' => [
            'name' => 'Manager',
            'description' => 'Rates the employees who report to them.',
            'is_system' => false,
            'permissions' => [PermissionName::ViewDashboard, PermissionName::ViewEmployees],
        ],
        'employee' => [
            'name' => 'Employee',
            'description' => 'Completes their own self-assessment.',
            'is_system' => false,
            'permissions' => [],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            );
        }

        foreach (self::ROLES as $slug => $definition) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'description' => $definition['description'], 'is_system' => $definition['is_system']],
            );

            if ($definition['permissions'] !== [] && $role->permissions()->doesntExist()) {
                $role->permissions()->sync(
                    Permission::whereIn('name', array_map(fn (PermissionName $p) => $p->value, $definition['permissions']))->pluck('id'),
                );
            }
        }
    }
}
