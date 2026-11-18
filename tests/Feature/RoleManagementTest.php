<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_seeder_creates_every_permission_and_default_roles(): void
    {
        $this->assertSame(count(PermissionName::cases()), Permission::count());
        $this->assertEqualsCanonicalizing(['admin', 'hr', 'manager', 'employee'], Role::pluck('slug')->all());
        $this->assertTrue(Role::where('slug', 'hr')->first()->hasPermission(PermissionName::ManageEmployees));
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $permissionIds = Permission::whereIn('name', [PermissionName::ViewDashboard->value, PermissionName::ViewEmployees->value])->pluck('id')->all();

        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), ['name' => 'Team Lead', 'description' => 'Leads a team', 'permissions' => $permissionIds])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Team Lead')->firstOrFail();

        $this->assertSame('team-lead', $role->slug);
        $this->assertEqualsCanonicalizing($permissionIds, $role->permissions->pluck('id')->all());
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $role = Role::factory()->withPermissions(PermissionName::ViewDashboard)->create();
        $manageRatings = Permission::where('name', PermissionName::ManageRatings->value)->value('id');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $role), ['name' => 'Appraisal Editor', 'permissions' => [$manageRatings]])
            ->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('Appraisal Editor', $role->name);
        $this->assertSame([$manageRatings], $role->permissions->pluck('id')->all());
    }

    public function test_admin_role_cannot_be_edited_or_deleted(): void
    {
        $adminRole = Role::where('slug', Role::SUPER_ADMIN_SLUG)->first();

        $this->actingAs($this->admin);

        $this->get(route('admin.roles.edit', $adminRole))->assertForbidden();
        $this->put(route('admin.roles.update', $adminRole), ['name' => 'Hacked'])->assertForbidden();
        $this->delete(route('admin.roles.destroy', $adminRole))->assertSessionHasErrors('role');
        $this->assertModelExists($adminRole);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::factory()->create();
        User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
        $this->assertModelExists($role);

        $unusedRole = Role::factory()->create();
        $this->delete(route('admin.roles.destroy', $unusedRole))->assertRedirect(route('admin.roles.index'));
        $this->assertModelMissing($unusedRole);
    }

    public function test_permissions_control_access_to_each_section(): void
    {
        $user = User::factory()->withPermissions(PermissionName::ManageRatings)->create();
        $department = Department::factory()->create();

        $this->actingAs($user);

        $this->get(route('admin.categories.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.employees.index'))->assertForbidden();
        $this->get(route('admin.departments.form', $department))->assertForbidden();
        $this->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_sidebar_only_shows_permitted_sections(): void
    {
        $user = User::factory()->withPermissions(PermissionName::ViewDashboard, PermissionName::ViewEmployees)->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Employees')
            ->assertSee(route('admin.employees.index'), false)
            ->assertDontSee(route('admin.roles.index'), false)
            ->assertDontSee(route('admin.categories.index'), false);
    }

    public function test_deactivated_user_loses_access(): void
    {
        $user = User::factory()->withPermissions(PermissionName::ViewDashboard)->inactive()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_role_pages_render(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.roles.index'))->assertOk()->assertSee('Full access');
        $this->get(route('admin.roles.create'))->assertOk()->assertSee('Manage roles &amp; permissions', false);
        $this->get(route('admin.roles.edit', Role::where('slug', 'hr')->first()))->assertOk();
    }
}
