<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RatingCategory;
use App\Models\RatingFactor;
use App\Models\User;
use Database\Seeders\AppraisalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppraisalStructureManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_seeder_creates_common_headings_and_kra_per_department(): void
    {
        $this->seed(AppraisalStructureSeeder::class);

        $this->assertSame(10, RatingCategory::whereNull('department_id')->count());
        $this->assertSame(47, RatingFactor::whereHas('category', fn ($query) => $query->whereNull('department_id'))->count());

        Department::all()->each(function (Department $department) {
            $this->assertTrue($department->ratingCategories()->where('code', 'K')->exists());
        });
    }

    public function test_admin_can_manage_departments(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), ['name' => 'Marketing'])
            ->assertRedirect();

        $department = Department::where('name', 'Marketing')->firstOrFail();

        $this->put(route('admin.departments.update', $department), ['name' => 'Digital Marketing'])
            ->assertRedirect(route('admin.departments.index'));

        $this->assertSame('Digital Marketing', $department->fresh()->name);
        $this->assertFalse($department->fresh()->is_active);

        $this->delete(route('admin.departments.destroy', $department))->assertRedirect();
        $this->assertModelMissing($department);
    }

    public function test_admin_can_create_department_specific_kra_heading_with_factors(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'department_id' => $department->id,
            'code' => 'K',
            'name' => 'Role Based KRA Achievement',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect();

        $category = RatingCategory::where('department_id', $department->id)->firstOrFail();

        $this->post(route('admin.categories.factors.store', $category), ['name' => 'Revenue Targets', 'sort_order' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('rating_factors', ['rating_category_id' => $category->id, 'name' => 'Revenue Targets']);
    }

    public function test_admin_can_update_and_delete_factor(): void
    {
        $factor = RatingFactor::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.factors.update', $factor), ['name' => 'Accuracy', 'sort_order' => 3, 'is_active' => '1'])
            ->assertRedirect(route('admin.categories.factors.index', $factor->rating_category_id));

        $this->assertSame('Accuracy', $factor->fresh()->name);

        $this->delete(route('admin.factors.destroy', $factor))->assertRedirect();
        $this->assertModelMissing($factor);
    }

    public function test_form_preview_shows_common_headings_and_only_own_department_kra(): void
    {
        $development = Department::factory()->create(['name' => 'Development']);
        $sales = Department::factory()->create(['name' => 'Sales']);

        $common = RatingCategory::factory()->create(['name' => 'Approach to work']);
        RatingFactor::factory()->for($common, 'category')->create(['name' => 'Follows instruction']);

        $developmentKra = RatingCategory::factory()->forDepartment($development)->create(['name' => 'Development KRA']);
        RatingFactor::factory()->for($developmentKra, 'category')->create(['name' => 'Ship sprint goals']);

        $salesKra = RatingCategory::factory()->forDepartment($sales)->create(['name' => 'Sales KRA']);
        RatingFactor::factory()->for($salesKra, 'category')->create(['name' => 'Close monthly deals']);

        $this->actingAs($this->admin)
            ->get(route('admin.departments.form', $development))
            ->assertOk()
            ->assertSeeInOrder(['Approach to work', 'Follows instruction', 'Development KRA', 'Ship sprint goals'])
            ->assertDontSee('Sales KRA')
            ->assertDontSee('Close monthly deals');
    }

    public function test_inactive_factors_are_hidden_from_form_preview(): void
    {
        $department = Department::factory()->create();
        $category = RatingCategory::factory()->create();
        RatingFactor::factory()->for($category, 'category')->inactive()->create(['name' => 'Retired factor']);

        $this->actingAs($this->admin)
            ->get(route('admin.departments.form', $department))
            ->assertOk()
            ->assertDontSee('Retired factor');
    }

    public function test_admin_pages_render(): void
    {
        $this->seed(AppraisalStructureSeeder::class);
        $category = RatingCategory::first();

        $this->actingAs($this->admin);

        $this->get(route('admin.departments.index'))->assertOk();
        $this->get(route('admin.categories.index'))->assertOk();
        $this->get(route('admin.categories.index', ['department' => 'common']))->assertOk();
        $this->get(route('admin.categories.create'))->assertOk();
        $this->get(route('admin.categories.edit', $category))->assertOk();
        $this->get(route('admin.categories.factors.index', $category))->assertOk();
        $this->get(route('admin.factors.edit', $category->factors->first()))->assertOk();
        $this->get(route('admin.departments.edit', Department::first()))->assertOk();
    }
}
