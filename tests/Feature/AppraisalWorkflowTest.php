<?php

namespace Tests\Feature;

use App\AppraisalStatus;
use App\Mail\AppraisalInvitationMail;
use App\Mail\AppraisalReadyForManagerMail;
use App\Models\Appraisal;
use App\Models\Department;
use App\Models\RatingCategory;
use App\Models\RatingFactor;
use App\Models\User;
use App\PermissionName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppraisalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An employee in a department whose form has two factors, reporting to an active manager.
     */
    private function employeeReadyForAppraisal(): User
    {
        $department = Department::factory()->create();
        $category = RatingCategory::factory()->create(['department_id' => null, 'code' => 'A', 'name' => 'Job Knowledge']);
        RatingFactor::factory()->count(2)->create(['rating_category_id' => $category->id]);

        return User::factory()->create([
            'department_id' => $department->id,
            'manager_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function ratingsFor(Appraisal $appraisal, int $value): array
    {
        return $appraisal->ratings->mapWithKeys(fn ($rating) => [$rating->id => $value])->all();
    }

    public function test_hr_sees_send_appraisal_page_with_eligible_employees(): void
    {
        $hr = User::factory()->withPermissions(PermissionName::SendAppraisals)->create();
        $employee = $this->employeeReadyForAppraisal();

        $this->actingAs($hr)
            ->get(route('admin.appraisals.create'))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee($employee->manager->name)
            ->assertSee('Ready');
    }

    public function test_sending_creates_appraisal_and_mails_employee_with_manager_in_cc(): void
    {
        Mail::fake();
        $hr = User::factory()->admin()->create();
        $employee = $this->employeeReadyForAppraisal();

        $this->actingAs($hr)
            ->post(route('admin.appraisals.store'), ['title' => 'Annual Appraisal 2026', 'employee_ids' => [$employee->id]])
            ->assertRedirect(route('admin.appraisals.index'))
            ->assertSessionHas('status', 'Appraisal mail sent to 1 employee.');

        $appraisal = Appraisal::sole();

        $this->assertSame(AppraisalStatus::Pending, $appraisal->status);
        $this->assertTrue($appraisal->manager->is($employee->manager));
        $this->assertCount(2, $appraisal->ratings);

        Mail::assertSent(AppraisalInvitationMail::class, function (AppraisalInvitationMail $mail) use ($employee) {
            return $mail->hasTo($employee->email)
                && $mail->hasCc($employee->manager->email)
                && str_contains($mail->render(), route('admin.appraisals.show', $mail->appraisal));
        });
    }

    public function test_employee_without_manager_cannot_be_sent_an_appraisal(): void
    {
        Mail::fake();
        $hr = User::factory()->admin()->create();
        $employee = $this->employeeReadyForAppraisal();
        $employee->update(['manager_id' => null]);

        $this->actingAs($hr)
            ->post(route('admin.appraisals.store'), ['title' => 'Annual Appraisal 2026', 'employee_ids' => [$employee->id]])
            ->assertSessionHasErrors('employee_ids');

        $this->assertDatabaseCount('appraisals', 0);
        Mail::assertNothingSent();
    }

    public function test_employee_with_open_appraisal_cannot_be_sent_another(): void
    {
        $hr = User::factory()->admin()->create();
        $employee = $this->employeeReadyForAppraisal();
        Appraisal::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($hr)
            ->post(route('admin.appraisals.store'), ['title' => 'Mid-year', 'employee_ids' => [$employee->id]])
            ->assertSessionHasErrors('employee_ids');
    }

    public function test_user_without_permission_cannot_send_appraisals(): void
    {
        $this->actingAs(User::factory()->withPermissions(PermissionName::ViewEmployees)->create())
            ->get(route('admin.appraisals.create'))
            ->assertForbidden();
    }

    public function test_mail_link_sends_guest_to_login_then_back_to_the_form(): void
    {
        $appraisal = Appraisal::factory()->withRatings()->create();
        $formUrl = route('admin.appraisals.show', $appraisal);

        $this->get($formUrl)->assertRedirect(route('login'));

        $this->post(route('login.store'), ['login' => $appraisal->employee->email, 'password' => 'password'])
            ->assertRedirect($formUrl);

        $this->assertAuthenticatedAs($appraisal->employee);
    }

    public function test_employee_with_no_permissions_but_an_appraisal_lands_on_my_appraisals(): void
    {
        $appraisal = Appraisal::factory()->create();

        $this->post(route('login.store'), ['login' => $appraisal->employee->email, 'password' => 'password'])
            ->assertRedirect(route('admin.appraisals.mine'));
    }

    public function test_my_appraisals_lists_own_and_managed_appraisals(): void
    {
        $manager = User::factory()->create();
        $ownAppraisal = Appraisal::factory()->create(['employee_id' => $manager->id, 'title' => 'Own review']);
        $teamAppraisal = Appraisal::factory()->employeeSubmitted()->create(['manager_id' => $manager->id, 'title' => 'Team review']);
        Appraisal::factory()->create(['title' => 'Someone else review']);

        $this->actingAs($manager)
            ->get(route('admin.appraisals.mine'))
            ->assertOk()
            ->assertSee(['Own review', 'Team review', 'My Appraisals'])
            ->assertSee($teamAppraisal->employee->name)
            ->assertDontSee('Someone else review');

        $this->assertTrue($ownAppraisal->employee->is($manager));
    }

    public function test_employee_submits_self_rating_and_manager_is_notified(): void
    {
        Mail::fake();
        $appraisal = Appraisal::factory()->withRatings(2)->create();

        $this->actingAs($appraisal->employee)
            ->get(route('admin.appraisals.show', $appraisal))
            ->assertOk()
            ->assertSee('Submit self-rating');

        $this->put(route('admin.appraisals.update', $appraisal), [
            'ratings' => $this->ratingsFor($appraisal, 4),
            'comments' => 'Delivered the billing module.',
        ])->assertRedirect(route('admin.appraisals.show', $appraisal));

        $appraisal->refresh();

        $this->assertSame(AppraisalStatus::EmployeeSubmitted, $appraisal->status);
        $this->assertSame('Delivered the billing module.', $appraisal->employee_comments);
        $this->assertEquals(4.0, $appraisal->averageRating('employee'));
        $this->assertNull($appraisal->averageRating('manager'));

        Mail::assertSent(AppraisalReadyForManagerMail::class, fn ($mail) => $mail->hasTo($appraisal->manager->email));
    }

    public function test_every_factor_must_be_rated_between_one_and_five(): void
    {
        $appraisal = Appraisal::factory()->withRatings(2)->create();
        [$first, $second] = $appraisal->ratings->all();

        $this->actingAs($appraisal->employee)
            ->put(route('admin.appraisals.update', $appraisal), ['ratings' => [$first->id => 6]])
            ->assertSessionHasErrors(["ratings.{$first->id}", "ratings.{$second->id}"]);

        $this->assertSame(AppraisalStatus::Pending, $appraisal->fresh()->status);
    }

    public function test_manager_cannot_rate_before_employee_submits(): void
    {
        $appraisal = Appraisal::factory()->withRatings(2)->create();

        $this->actingAs($appraisal->manager)
            ->put(route('admin.appraisals.update', $appraisal), ['ratings' => $this->ratingsFor($appraisal, 3)])
            ->assertForbidden();
    }

    public function test_manager_rates_after_employee_and_appraisal_completes(): void
    {
        $appraisal = Appraisal::factory()->employeeSubmitted()->withRatings(2)->create();

        $this->actingAs($appraisal->manager)
            ->get(route('admin.appraisals.show', $appraisal))
            ->assertOk()
            ->assertSee('Submit manager rating');

        $this->put(route('admin.appraisals.update', $appraisal), ['ratings' => $this->ratingsFor($appraisal, 3)])
            ->assertRedirect(route('admin.appraisals.show', $appraisal));

        $appraisal->refresh();

        $this->assertSame(AppraisalStatus::Completed, $appraisal->status);
        $this->assertNotNull($appraisal->manager_submitted_at);
        $this->assertEquals(3.0, $appraisal->averageRating('manager'));
    }

    public function test_employee_cannot_rate_again_after_submitting(): void
    {
        $appraisal = Appraisal::factory()->employeeSubmitted()->withRatings(1)->create();

        $this->actingAs($appraisal->employee)
            ->put(route('admin.appraisals.update', $appraisal), ['ratings' => $this->ratingsFor($appraisal, 5)])
            ->assertForbidden();
    }

    public function test_other_employees_cannot_open_someone_elses_appraisal(): void
    {
        $appraisal = Appraisal::factory()->create();

        $this->actingAs(User::factory()->withPermissions(PermissionName::ViewEmployees)->create())
            ->get(route('admin.appraisals.show', $appraisal))
            ->assertForbidden();
    }

    public function test_hr_can_add_optional_remarks_at_any_stage(): void
    {
        $hr = User::factory()->withPermissions(PermissionName::SendAppraisals)->create();
        $appraisal = Appraisal::factory()->completed()->withRatings()->create();

        $this->actingAs($hr)
            ->put(route('admin.appraisals.hr-remarks', $appraisal), ['hr_remarks' => 'Recommended for promotion.'])
            ->assertRedirect(route('admin.appraisals.show', $appraisal));

        $appraisal->refresh();

        $this->assertSame('Recommended for promotion.', $appraisal->hr_remarks);
        $this->assertTrue($appraisal->hrRemarksAuthor->is($hr));
        $this->assertNotNull($appraisal->hr_remarked_at);

        $this->actingAs($appraisal->employee)
            ->get(route('admin.appraisals.show', $appraisal))
            ->assertSee('Recommended for promotion.')
            ->assertDontSee('Save HR remarks');
    }

    public function test_hr_remarks_can_be_cleared(): void
    {
        $hr = User::factory()->withPermissions(PermissionName::SendAppraisals)->create();
        $appraisal = Appraisal::factory()->create(['hr_remarks' => 'Old note', 'hr_remarks_by' => $hr->id, 'hr_remarked_at' => now()]);

        $this->actingAs($hr)->put(route('admin.appraisals.hr-remarks', $appraisal), ['hr_remarks' => '']);

        $this->assertNull($appraisal->fresh()->hr_remarks);
        $this->assertNull($appraisal->fresh()->hr_remarks_by);
    }

    public function test_employee_and_manager_cannot_save_hr_remarks(): void
    {
        $appraisal = Appraisal::factory()->create();

        foreach ([$appraisal->employee, $appraisal->manager] as $user) {
            $this->actingAs($user)
                ->put(route('admin.appraisals.hr-remarks', $appraisal), ['hr_remarks' => 'Sneaky'])
                ->assertForbidden();
        }

        $this->assertNull($appraisal->fresh()->hr_remarks);
    }

    public function test_hr_can_list_and_view_all_appraisals(): void
    {
        $hr = User::factory()->withPermissions(PermissionName::SendAppraisals)->create();
        $appraisal = Appraisal::factory()->completed()->withRatings()->create(['hr_remarked_at' => '2026-11-10 10:00:00']);

        $this->actingAs($hr)
            ->get(route('admin.appraisals.index'))
            ->assertOk()
            ->assertSee($appraisal->employee->name)
            ->assertSee('Completed')
            ->assertSeeInOrder(['<th>HR</th>', '10 Nov 2026'], false);

        $this->get(route('admin.appraisals.show', $appraisal))
            ->assertOk()
            ->assertDontSee('Submit self-rating');
    }
}
