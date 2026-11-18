<?php

namespace Tests\Feature;

use App\Mail\EmployeeCredentialsMail;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\PermissionName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'employee_code' => 'EMP2001',
            'name' => 'Riya Sharma',
            'email' => 'riya@example.com',
            'designation' => 'Developer',
            'department_id' => Department::factory()->create()->id,
            'role_id' => Role::factory()->create()->id,
            'manager_id' => null,
            'joining_date' => '2024-04-01',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_can_list_and_search_employees(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Aman Verma']);
        User::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['search' => 'Aman']))
            ->assertOk()
            ->assertSee('Aman Verma')
            ->assertDontSee('Someone Else');
    }

    public function test_admin_can_create_employee(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), $this->validPayload(['manager_id' => $admin->id]))
            ->assertRedirect(route('admin.employees.index'));

        $employee = User::where('employee_code', 'EMP2001')->firstOrFail();

        $this->assertSame('riya@example.com', $employee->email);
        $this->assertTrue($employee->manager->is($admin));
        $this->assertTrue($employee->is_active);

        Mail::assertSent(EmployeeCredentialsMail::class, function (EmployeeCredentialsMail $mail) use ($employee) {
            $body = $mail->render();

            return $mail->hasTo('riya@example.com')
                && strlen($mail->plainPassword) === 10
                && Hash::check($mail->plainPassword, $employee->password)
                && str_contains($body, 'EMP2001')
                && str_contains($body, $mail->plainPassword);
        });
    }

    public function test_each_new_employee_gets_a_different_random_password(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.employees.store'), $this->validPayload());
        $this->post(route('admin.employees.store'), $this->validPayload(['employee_code' => 'EMP2002', 'email' => 'second@example.com']));

        $passwords = Mail::sent(EmployeeCredentialsMail::class)->map(fn (EmployeeCredentialsMail $mail) => $mail->plainPassword);

        $this->assertCount(2, $passwords);
        $this->assertNotSame($passwords[0], $passwords[1]);
    }

    public function test_employee_is_not_created_when_credentials_mail_fails(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('Connection refused'));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertSessionHasErrors('employee');

        $this->assertDatabaseMissing('users', ['employee_code' => 'EMP2001']);
    }

    public function test_admin_can_send_new_credentials_to_an_employee(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.employees.index'))
            ->post(route('admin.employees.send-credentials', $employee))
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('status');

        $this->assertFalse(Hash::check('password', $employee->fresh()->password));

        Mail::assertSent(EmployeeCredentialsMail::class, fn (EmployeeCredentialsMail $mail) => $mail->hasTo($employee->email)
            && Hash::check($mail->plainPassword, $employee->fresh()->password));
    }

    public function test_password_is_kept_when_resending_credentials_fails(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('Connection refused'));
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.employees.send-credentials', $employee))
            ->assertSessionHasErrors('employee');

        $this->assertTrue(Hash::check('password', $employee->fresh()->password));
    }

    public function test_viewer_cannot_send_credentials(): void
    {
        $viewer = User::factory()->withPermissions(PermissionName::ViewEmployees)->create();

        $this->actingAs($viewer)
            ->post(route('admin.employees.send-credentials', User::factory()->create()))
            ->assertForbidden();
    }

    public function test_employee_code_and_email_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['employee_code' => 'EMP2001', 'email' => 'riya@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertSessionHasErrors(['employee_code', 'email']);
    }

    public function test_updating_an_employee_does_not_change_their_password(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $originalHash = $employee->password;

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), $this->validPayload([
                'employee_code' => $employee->employee_code,
                'email' => $employee->email,
                'name' => 'Renamed Person',
            ]))
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertSame('Renamed Person', $employee->name);
        $this->assertSame($originalHash, $employee->password);
    }

    public function test_employee_cannot_be_their_own_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), $this->validPayload([
                'employee_code' => $employee->employee_code,
                'email' => $employee->email,
                'manager_id' => $employee->id,
            ]))
            ->assertSessionHasErrors('manager_id');
    }

    public function test_admin_cannot_change_own_role_or_deactivate_self(): void
    {
        $admin = User::factory()->admin()->create();
        $adminRoleId = $admin->role_id;

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $admin), $this->validPayload([
                'employee_code' => $admin->employee_code,
                'email' => $admin->email,
                'role_id' => Role::factory()->create()->id,
                'is_active' => null,
            ]))
            ->assertRedirect(route('admin.employees.index'));

        $admin->refresh();
        $this->assertSame($adminRoleId, $admin->role_id);
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_cannot_delete_self_but_can_delete_others(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin)->delete(route('admin.employees.destroy', $admin))->assertSessionHasErrors('employee');
        $this->assertModelExists($admin);

        $this->delete(route('admin.employees.destroy', $employee))->assertRedirect(route('admin.employees.index'));
        $this->assertModelMissing($employee);
    }

    public function test_viewer_can_list_but_not_create_employees(): void
    {
        $viewer = User::factory()->withPermissions(PermissionName::ViewEmployees)->create();

        $this->actingAs($viewer);

        $this->get(route('admin.employees.index'))->assertOk()->assertDontSee('Add employee');
        $this->get(route('admin.employees.create'))->assertForbidden();
        $this->post(route('admin.employees.store'), $this->validPayload())->assertForbidden();
    }

    public function test_non_super_admin_cannot_assign_admin_role_or_edit_an_admin(): void
    {
        $hr = User::factory()->withPermissions(PermissionName::ViewEmployees, PermissionName::ManageEmployees)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($hr)
            ->post(route('admin.employees.store'), $this->validPayload(['role_id' => $admin->role_id]))
            ->assertSessionHasErrors('role_id');

        $this->get(route('admin.employees.edit', $admin))->assertForbidden();
        $this->delete(route('admin.employees.destroy', $admin))->assertForbidden();
    }

    public function test_validation_errors_are_shown_next_to_their_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.employees.create'))
            ->followingRedirects()
            ->post(route('admin.employees.store'), $this->validPayload(['email' => 'not-an-email']))
            ->assertOk()
            ->assertSee('Please fix the highlighted fields below.')
            ->assertSee('field field-full has-error', false)
            ->assertSee('The email field must be a valid email address.');
    }

    public function test_employee_forms_render(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get(route('admin.employees.create'))->assertOk()->assertSee('A random password is generated')->assertDontSee('name="password"', false);
        $this->get(route('admin.employees.edit', User::factory()->create()))->assertOk();
        $this->get(route('admin.employees.edit', $admin))->assertOk()->assertSee('You cannot change your own role.');
    }
}
