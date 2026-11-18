<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\PermissionName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_profile(): void
    {
        $this->get(route('admin.profile.show'))->assertRedirect(route('login'));
    }

    public function test_user_sees_own_details_team_and_permissions(): void
    {
        $department = Department::factory()->create(['name' => 'Development']);
        $manager = User::factory()->create(['name' => 'Kiran Rao']);
        $user = User::factory()
            ->withPermissions(PermissionName::ViewEmployees)
            ->create(['name' => 'Neha Verma', 'department_id' => $department->id, 'manager_id' => $manager->id]);
        User::factory()->create(['name' => 'Team Member', 'manager_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertSee('Neha Verma')
            ->assertSee('Development')
            ->assertSee('Kiran Rao')
            ->assertSee('Team Member')
            ->assertSee(PermissionName::ViewEmployees->label())
            ->assertDontSee(PermissionName::ManageRoles->label());
    }

    public function test_header_avatar_links_to_profile(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.profile.show'), false);
    }

    public function test_user_can_update_name_and_email_only(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->withPermissions(PermissionName::ViewDashboard)->create(['department_id' => $department->id]);
        $roleId = $user->role_id;

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'role_id' => 999,
                'department_id' => null,
            ])
            ->assertRedirect(route('admin.profile.show'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame($roleId, $user->role_id);
        $this->assertSame($department->id, $user->department_id);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.profile.update'), ['name' => $user->name, 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.profile.password'), [
                'current_password' => 'password',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertRedirect(route('admin.profile.show'));

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.profile.password'), [
                'current_password' => 'wrong',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
