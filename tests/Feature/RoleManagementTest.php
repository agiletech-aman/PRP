<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingsController;
use App\Mail\SmtpTestMail;
use App\Models\AppraisalSetting;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $this->assertTrue(Role::where('slug', 'hr')->first()->hasPermission(PermissionName::ViewPerformanceReports));
        $this->assertTrue(Role::where('slug', 'hr')->first()->hasPermission(PermissionName::ManageAppraisalSettings));
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

    public function test_role_form_registers_new_permissions_when_database_rows_are_stale(): void
    {
        $permissionIds = Permission::whereIn('name', [
            PermissionName::ViewPerformanceReports->value,
            PermissionName::ManageAppraisalSettings->value,
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        Permission::whereIn('id', $permissionIds)->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee(PermissionName::ViewPerformanceReports->label())
            ->assertSee(PermissionName::ManageAppraisalSettings->label());

        $this->assertDatabaseHas('permissions', ['name' => PermissionName::ViewPerformanceReports->value]);
        $this->assertDatabaseHas('permissions', ['name' => PermissionName::ManageAppraisalSettings->value]);
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

    public function test_appraisal_settings_permission_controls_settings_page(): void
    {
        $settingsUser = User::factory()->withPermissions(
            PermissionName::ManageAppraisalSettings,
            PermissionName::ViewDashboard,
        )->create();

        $this->actingAs($settingsUser)
            ->get(route('admin.settings.appraisal'))
            ->assertOk()
            ->assertSee('HR evaluation report mail recipients')
            ->assertSee('SMTP setup')
            ->assertSee('Send test email')
            ->assertDontSee('Post-manager mail recipients');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.appraisal'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.smtp.update'))
            ->assertForbidden();
    }

    public function test_user_can_save_dark_theme_and_collapsed_sidebar(): void
    {
        $settingsUser = User::factory()->withPermissions(
            PermissionName::ManageAppraisalSettings,
            PermissionName::ViewDashboard,
        )->create();

        $this->actingAs($settingsUser)
            ->put(route('admin.settings.appraisal.update'), [
                'theme_mode' => 'dark',
                'sidebar_collapsed' => '1',
                'report_recipient_emails' => "director@example.com,\nhr@example.com",
            ])
            ->assertRedirect();

        $this->assertSame(
            'dark',
            AppraisalSetting::get(SettingsController::themeKey($settingsUser->id), 'light'),
        );
        $this->assertSame(
            '1',
            AppraisalSetting::get(SettingsController::sidebarKey($settingsUser->id), '0'),
        );
        $this->assertSame(
            ['director@example.com', 'hr@example.com'],
            AppraisalSetting::getJson(SettingsController::HR_REPORT_RECIPIENTS_KEY, []),
        );

        $this->actingAs($settingsUser)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('sidebar-collapsed', false);
    }

    public function test_smtp_settings_are_encrypted_and_applied_on_later_requests(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.smtp.update'), [
                'action' => 'save',
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => '465',
                'smtp_encryption' => 'ssl',
                'smtp_username' => 'mailer@example.com',
                'smtp_password' => 'test-secret',
                'smtp_from_address' => 'mailer@example.com',
                'smtp_from_name' => 'Appraisal Portal',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'SMTP settings saved.');

        $storedPassword = AppraisalSetting::get('smtp_password');
        $this->assertNotSame('test-secret', $storedPassword);
        $this->assertSame('test-secret', Crypt::decryptString($storedPassword));

        $this->actingAs($this->admin)
            ->get(route('admin.settings.appraisal'))
            ->assertOk();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('mailer@example.com', config('mail.from.address'));
    }

    public function test_smtp_test_email_uses_submitted_settings_without_saving_them(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.smtp.update'), [
                'action' => 'test',
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_from_address' => 'mailer@example.com',
                'smtp_from_name' => 'Appraisal Portal',
                'test_recipient' => 'recipient@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'SMTP test email sent successfully.');

        Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $mail) => $mail->hasTo('recipient@example.com'));
        $this->assertDatabaseMissing('appraisal_settings', ['key' => 'smtp_host']);
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
