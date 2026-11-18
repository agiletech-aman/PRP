<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My Appraisals" in the portal: the appraisals the signed-in user takes part in.
 * The form itself lives on the standalone appraisal pages (AppraisalFormController).
 */
class AppraisalReviewController extends Controller
{
    /**
     * Appraisals the signed-in user fills in, as the employee or as the manager.
     */
    public function index(Request $request): View
    {
        return view('admin.appraisals.mine', [
            'appraisals' => Appraisal::query()
                ->involving($request->user())
                ->with(['employee', 'manager'])
                ->latest('sent_at')
                ->latest('id')
                ->get(),
        ]);
    }
}
