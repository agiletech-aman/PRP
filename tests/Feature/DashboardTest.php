<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\PermissionName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_company_overview(): void
    {
        Department::factory()->create(['name' => 'Sales']);
        User::factory()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard.organisation')
            ->assertViewHas('employeeCount', 4)
            ->assertSee('Performance Review Portal')
            ->assertSee('Sales')
            ->assertSee(route('admin.departments.index'), false);
    }

    public function test_hr_without_department_permission_gets_no_department_links(): void
    {
        $hr = User::factory()->withPermissions(
            PermissionName::ViewDashboard,
            PermissionName::ViewEmployees,
            PermissionName::ManageEmployees,
        )->create();

        $this->actingAs($hr)
            ->get(route('admin.dashboard'))
            ->assertViewIs('admin.dashboard.organisation')
            ->assertSee(route('admin.employees.index'), false)
            ->assertDontSee(route('admin.departments.index'), false)
            ->assertDontSee(route('admin.categories.index'), false);
    }

    public function test_manager_sees_only_their_own_team(): void
    {
        $department = Department::factory()->create(['name' => 'Development']);
        $manager = User::factory()
            ->withPermissions(PermissionName::ViewDashboard, PermissionName::ViewEmployees)
            ->create(['department_id' => $department->id]);

        User::factory()->create(['name' => 'Asha Team', 'manager_id' => $manager->id, 'designation' => 'Developer']);
        User::factory()->inactive()->create(['name' => 'Ravi Team', 'manager_id' => $manager->id, 'designation' => 'Developer']);
        User::factory()->create(['name' => 'Outside Person']);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard.team')
            ->assertViewHas('teamSize', 2)
            ->assertViewHas('activeTeamCount', 1)
            ->assertSee('Your team overview')
            ->assertSee('Asha Team')
            ->assertSee('Ravi Team')
            ->assertDontSee('Outside Person')
            ->assertSee(route('admin.employees.index', ['manager' => $manager->id]), false);
    }

    public function test_team_dashboard_without_employee_permission_has_no_employee_links(): void
    {
        $user = User::factory()->withPermissions(PermissionName::ViewDashboard)->create();
        User::factory()->create(['manager_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertViewIs('admin.dashboard.team')
            ->assertDontSee(route('admin.employees.index'), false);
    }

    public function test_needs_attention_lists_setup_gaps_with_links(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $manager = User::factory()->create(['department_id' => $department->id]);
        User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
        User::factory()->create(['department_id' => null, 'manager_id' => $manager->id]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $items = collect($response->viewData('attentionItems'))->keyBy('label');

        $this->assertSame(1, $items['Employees without a reporting manager']['count']);
        $this->assertSame(1, $items['Employees without a department']['count']);
        $this->assertSame(1, $items['Departments without a KRA heading']['count']);
        $response->assertSee(route('admin.employees.index', ['manager' => 'none']), false);
    }

    public function test_joining_trend_counts_people_per_month(): void
    {
        $admin = User::factory()->admin()->create(['joining_date' => now()->subYears(3)]);
        User::factory()->count(2)->create(['joining_date' => now()->startOfMonth()->addDay()]);

        $trend = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('joiningTrend');

        $this->assertCount(12, $trend);
        $this->assertSame(2, end($trend)['count']);
        $this->assertSame(100, end($trend)['height']);
    }

    public function test_employee_list_can_show_people_without_manager_department_or_inactive(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'The Admin']);
        $department = Department::factory()->create();
        User::factory()->create(['name' => 'Has Everything', 'department_id' => $department->id, 'manager_id' => $admin->id]);
        User::factory()->create(['name' => 'No Department', 'department_id' => null, 'manager_id' => $admin->id]);
        User::factory()->inactive()->create(['name' => 'Gone Person', 'department_id' => $department->id, 'manager_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('admin.employees.index', ['department' => 'none']))
            ->assertSee('No Department')->assertDontSee('Has Everything');

        $this->get(route('admin.employees.index', ['manager' => 'none']))
            ->assertSee('No reporting manager')->assertSee('The Admin')->assertDontSee('Has Everything');

        $this->get(route('admin.employees.index', ['status' => 'inactive']))
            ->assertSee('Gone Person')->assertDontSee('Has Everything');
    }

    public function test_employee_list_can_be_filtered_by_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['name' => 'Team Boss']);
        User::factory()->create(['name' => 'Direct Report', 'manager_id' => $manager->id]);
        User::factory()->create(['name' => 'Someone Unrelated']);

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['manager' => $manager->id]))
            ->assertOk()
            ->assertSee('Reporting to Team Boss')
            ->assertSee('Direct Report')
            ->assertDontSee('Someone Unrelated');
    }
}
