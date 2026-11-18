<?php

namespace App\Http\Controllers\Admin;

use App\AppraisalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendAppraisalRequest;
use App\Mail\AppraisalInvitationMail;
use App\Models\Appraisal;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * HR side of appraisals: track every appraisal and send new ones by mail.
 */
class AppraisalController extends Controller
{
    /**
     * List all appraisals; filter with ?search= (employee name/code) and ?status=.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = AppraisalStatus::tryFrom((string) $request->query('status'));

        return view('admin.appraisals.index', [
            'appraisals' => Appraisal::query()
                ->with(['employee', 'manager', 'department'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->whereHas('employee', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%");
                    });
                })
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('sent_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => AppraisalStatus::cases(),
        ]);
    }

    /**
     * Pick the employees who should receive an appraisal mail; filter with ?search= and ?department=.
     */
    public function create(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $employees = User::query()
            ->with(['department', 'manager'])
            ->withExists(['appraisals as has_open_appraisal' => fn ($query) => $query->open()])
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', (int) $request->query('department')))
            ->orderBy('name')
            ->get();

        return view('admin.appraisals.create', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'defaultTitle' => 'Annual Appraisal '.now()->year,
        ]);
    }

    /**
     * Open an appraisal for each selected employee and mail them, with their manager in CC.
     *
     * Each appraisal is only kept if its mail was sent. Sending stops at the first mail failure,
     * since that almost always means the mail settings are wrong and every other mail would fail too.
     */
    public function store(SendAppraisalRequest $request): RedirectResponse
    {
        $sentCount = 0;

        foreach ($request->selectedEmployees() as $employee) {
            try {
                DB::transaction(function () use ($employee, $request) {
                    $appraisal = Appraisal::startFor($employee, $request->validated('title'), $request->user());

                    Mail::send(new AppraisalInvitationMail($appraisal->load(['employee', 'manager'])));
                });
            } catch (TransportExceptionInterface $exception) {
                report($exception);

                $redirect = $sentCount > 0
                    ? redirect()->route('admin.appraisals.index')->with('status', "Appraisal mail sent to {$sentCount} ".Str::plural('employee', $sentCount).'.')
                    : back()->withInput();

                return $redirect->withErrors([
                    'employee_ids' => "The appraisal mail to {$employee->name} could not be sent, so no appraisal was created for them or anyone after them. Please check the mail settings and try again.",
                ]);
            }

            $sentCount++;
        }

        return redirect()
            ->route('admin.appraisals.index')
            ->with('status', "Appraisal mail sent to {$sentCount} ".Str::plural('employee', $sentCount).'.');
    }
}
