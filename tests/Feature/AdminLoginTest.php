<?php

namespace Tests\Feature;

use App\Models\User;
use App\PermissionName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Employee ID or email');
    }

    public function test_admin_can_log_in_with_email(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.store'), ['login' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_log_in_with_employee_code(): void
    {
        $admin = User::factory()->admin()->create(['employee_code' => 'EMP1024']);

        $this->post(route('login.store'), ['login' => 'EMP1024', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->from(route('login'))
            ->post(route('login.store'), ['login' => $admin->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_user_without_any_permission_cannot_log_in(): void
    {
        $employee = User::factory()->create();

        $this->post(route('login.store'), ['login' => $employee->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->post(route('login.store'), ['login' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_user_with_only_employee_permissions_lands_on_employees_page(): void
    {
        $user = User::factory()->withPermissions(PermissionName::ViewEmployees)->create();

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_sees_dashboard_and_can_log_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Departments');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
