<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveHrRemarksRequest;
use App\Http\Requests\SubmitAppraisalRatingsRequest;
use App\Mail\AppraisalReadyForManagerMail;
use App\Models\Appraisal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The standalone appraisal form that the mail links open: sign in, see the form for your role
 * (employee, manager or HR), fill in your part, and land on a thank-you page.
 */
class AppraisalFormController extends Controller
{
    /**
     * Sign-in page for this appraisal. After signing in the user comes straight back to the form.
     */
    public function login(Request $request, Appraisal $appraisal): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('appraisal.form', $appraisal);
        }

        redirect()->setIntendedUrl(route('appraisal.form', $appraisal));

        return view('appraisal.login', ['appraisal' => $appraisal]);
    }

    /**
     * The form, shown according to who signed in. Guests are sent to this appraisal's sign-in page.
     */
    public function show(Request $request, Appraisal $appraisal): View|Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('appraisal.login', $appraisal);
        }

        if ($user->cannot('view', $appraisal)) {
            return response()->view('appraisal.not-yours', ['appraisal' => $appraisal], 403);
        }

        $appraisal->load(['employee.department', 'manager', 'department', 'ratings', 'hrRemarksAuthor']);

        return view('appraisal.form', [
            'appraisal' => $appraisal,
            'role' => $appraisal->participantRoleFor($user),
            'stage' => $appraisal->ratingStageFor($user),
            'ratingsByCategory' => $appraisal->ratings->groupBy(fn ($rating) => $rating->category_code.'|'.$rating->category_name),
        ]);
    }

    public function submit(SubmitAppraisalRatingsRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $stage = $appraisal->ratingStageFor($request->user());

        $appraisal->submitRatings($stage, $request->validated('ratings'), $request->validated('comments'));

        if ($stage === 'employee' && $appraisal->manager) {
            $this->mailManagerTheirTurn($appraisal->load(['employee', 'manager']));
        }

        return redirect()->route('appraisal.thank-you', $appraisal)->with('submitted', $stage);
    }

    /**
     * Save HR's optional remarks; clearing the box removes them.
     */
    public function saveHrRemarks(SaveHrRemarksRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $remarks = $request->validated('hr_remarks');

        $appraisal->update([
            'hr_remarks' => $remarks,
            'hr_remarks_by' => $remarks === null ? null : $request->user()->id,
            'hr_remarked_at' => $remarks === null ? null : now(),
        ]);

        return redirect()->route('appraisal.thank-you', $appraisal)->with('submitted', 'hr');
    }

    public function thankYou(Request $request, Appraisal $appraisal): View
    {
        abort_unless($request->user()->can('view', $appraisal), 403);

        return view('appraisal.thank-you', [
            'appraisal' => $appraisal->load(['employee', 'manager']),
            'submitted' => session('submitted'),
        ]);
    }

    /**
     * Sign out and return to this appraisal's sign-in page, e.g. when the link was opened by the wrong person.
     */
    public function switchUser(Request $request, Appraisal $appraisal): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('appraisal.login', $appraisal);
    }

    /**
     * Tell the manager it is their turn, with their login details. The manager gets a new random password,
     * changed only if the mail carrying it was sent. Super admins and the person who sent the appraisal
     * keep their current password.
     */
    private function mailManagerTheirTurn(Appraisal $appraisal): void
    {
        $manager = $appraisal->manager;

        DB::transaction(function () use ($appraisal, $manager) {
            $plainPassword = null;

            if (! $manager->isSuperAdmin() && $manager->id !== $appraisal->sent_by) {
                $plainPassword = User::generatePassword();
                $manager->update(['password' => $plainPassword]);
            }

            Mail::send(new AppraisalReadyForManagerMail($appraisal, $plainPassword));
        });
    }
}
