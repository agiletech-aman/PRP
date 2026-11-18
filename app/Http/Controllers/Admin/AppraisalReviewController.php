<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveHrRemarksRequest;
use App\Http\Requests\SubmitAppraisalRatingsRequest;
use App\Mail\AppraisalReadyForManagerMail;
use App\Models\Appraisal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Employee and manager side of appraisals: open the form from the mail link and submit ratings.
 */
class AppraisalReviewController extends Controller
{
    /**
     * Appraisals the signed-in user fills in, as the employee or as the manager.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.appraisals.mine', [
            'appraisals' => Appraisal::query()
                ->involving($user)
                ->with(['employee', 'manager'])
                ->latest('sent_at')
                ->latest('id')
                ->get(),
        ]);
    }

    public function show(Request $request, Appraisal $appraisal): View
    {
        $appraisal->load(['employee.department', 'manager', 'ratings', 'hrRemarksAuthor']);

        return view('admin.appraisals.show', [
            'appraisal' => $appraisal,
            'stage' => $appraisal->ratingStageFor($request->user()),
            'ratingsByCategory' => $appraisal->ratings->groupBy(fn ($rating) => $rating->category_code.'|'.$rating->category_name),
        ]);
    }

    /**
     * Save HR's optional remarks; clearing the box removes them.
     */
    public function updateHrRemarks(SaveHrRemarksRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $remarks = $request->validated('hr_remarks');

        $appraisal->update([
            'hr_remarks' => $remarks,
            'hr_remarks_by' => $remarks === null ? null : $request->user()->id,
            'hr_remarked_at' => $remarks === null ? null : now(),
        ]);

        return redirect()->route('admin.appraisals.show', $appraisal)->with('status', 'HR remarks saved.');
    }

    public function update(SubmitAppraisalRatingsRequest $request, Appraisal $appraisal): RedirectResponse
    {
        $stage = $appraisal->ratingStageFor($request->user());

        $appraisal->submitRatings($stage, $request->validated('ratings'), $request->validated('comments'));

        if ($stage === 'employee' && $appraisal->manager) {
            Mail::send(new AppraisalReadyForManagerMail($appraisal->load(['employee', 'manager'])));
        }

        return redirect()
            ->route('admin.appraisals.show', $appraisal)
            ->with('status', $stage === 'employee'
                ? 'Self-appraisal submitted. Your manager has been notified.'
                : 'Manager ratings submitted. This appraisal is now complete.');
    }
}
