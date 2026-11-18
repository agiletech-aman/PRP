<?php

namespace App\Http\Controllers\Admin;

use App\AppraisalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendAppraisalRequest;
use App\Mail\AppraisalHrNoticeMail;
use App\Mail\AppraisalInvitationMail;
use App\Mail\AppraisalManagerNoticeMail;
use App\Models\Appraisal;
use App\Models\Department;
use App\Models\User;
use App\PermissionName;
use Illuminate\Database\Eloquent\Collection;
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
     * Open an appraisal for each selected employee. The employee gets their Employee ID and a new random
     * password (issued once per user per batch). The manager only gets a notice now; their login details
     * follow once the employee has submitted.
     *
     * An appraisal is only kept if the employee's mail was sent, and a password only changes if the mail
     * carrying it was sent. Sending stops at the first mail failure, since that almost always means the
     * mail settings are wrong and every other mail would fail too.
     */
    public function store(SendAppraisalRequest $request): RedirectResponse
    {
        $sender = $request->user();
        $issuedPasswords = [];
        $sentAppraisals = collect();
        $sentCount = 0;

        foreach ($request->selectedEmployees() as $employee) {
            try {
                $appraisal = DB::transaction(function () use ($employee, $request, $sender, &$issuedPasswords) {
                    $appraisal = Appraisal::startFor($employee, $request->validated('title'), $sender);
                    $appraisal->load(['employee', 'manager']);

                    Mail::send(new AppraisalInvitationMail($appraisal, $this->issuePassword($appraisal->employee, $sender, $issuedPasswords)));

                    return $appraisal;
                });
            } catch (TransportExceptionInterface $exception) {
                report($exception);

                return $this->stopSending($sentCount, "The appraisal mail to {$employee->name} could not be sent, so no appraisal was created for them or anyone after them.");
            }

            $sentCount++;
            $sentAppraisals->push($appraisal);

            try {
                Mail::send(new AppraisalManagerNoticeMail($appraisal));
            } catch (TransportExceptionInterface $exception) {
                report($exception);

                return $this->stopSending($sentCount, "{$employee->name} received their appraisal mail, but the mail to their manager {$appraisal->manager->name} could not be sent. Nobody after them was sent an appraisal.");
            }
        }

        foreach ($this->hrUsers() as $hr) {
            try {
                DB::transaction(function () use ($hr, $sentAppraisals, $sender, &$issuedPasswords) {
                    Mail::send(new AppraisalHrNoticeMail($hr, $sentAppraisals, $this->issuePassword($hr, $sender, $issuedPasswords)));
                });
            } catch (TransportExceptionInterface $exception) {
                report($exception);

                return $this->stopSending($sentCount, "All employees and managers were mailed, but the summary mail to HR ({$hr->name}) could not be sent.");
            }
        }

        return redirect()
            ->route('admin.appraisals.index')
            ->with('status', "Appraisal mail sent to {$sentCount} ".Str::plural('employee', $sentCount).', their managers and HR.');
    }

    /**
     * Active users who handle appraisals for HR: anyone whose role grants the send-appraisals permission.
     * Super admins are left out; they hold every permission implicitly and are not HR.
     *
     * @return Collection<int, User>
     */
    private function hrUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role.permissions', fn ($query) => $query->where('name', PermissionName::SendAppraisals->value))
            ->orderBy('name')
            ->get();
    }

    /**
     * Give the user a new random password, at most once per batch. Returns null when they keep their
     * current password: the person sending the appraisals and super admins are never reset.
     *
     * @param  array<int, string|null>  $issuedPasswords  Password already issued in this batch, keyed by user id.
     */
    private function issuePassword(User $user, User $sender, array &$issuedPasswords): ?string
    {
        if (array_key_exists($user->id, $issuedPasswords)) {
            return $issuedPasswords[$user->id];
        }

        if ($user->is($sender) || $user->isSuperAdmin()) {
            return $issuedPasswords[$user->id] = null;
        }

        $plainPassword = User::generatePassword();
        $user->update(['password' => $plainPassword]);

        return $issuedPasswords[$user->id] = $plainPassword;
    }

    /**
     * Redirect after a mail failure, keeping the success count for the mails that did go out.
     */
    private function stopSending(int $sentCount, string $problem): RedirectResponse
    {
        $redirect = $sentCount > 0
            ? redirect()->route('admin.appraisals.index')->with('status', "Appraisal mail sent to {$sentCount} ".Str::plural('employee', $sentCount).'.')
            : back()->withInput();

        return $redirect->withErrors(['employee_ids' => "{$problem} Please check the mail settings and try again."]);
    }
}
