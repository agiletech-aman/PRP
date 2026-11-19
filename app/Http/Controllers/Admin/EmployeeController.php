<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportEmployeesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Mail\EmployeeCredentialsMail;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\EmployeeImportSpreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmployeeController extends Controller
{
    private const MAIL_FAILED_MESSAGE = 'The login details mail could not be sent. Please check the mail settings and try again.';

    private const IMPORT_HEADERS = [
        'employee_code',
        'name',
        'email',
        'designation',
        'department',
        'role',
        'manager_employee_code',
        'joining_date',
        'is_active',
    ];

    /**
     * List employees; filter with ?search=, ?department=, ?role=, ?manager= (an id, or "none") and ?status=.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $employees = User::query()
            ->with(['role', 'department', 'manager'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('department'), fn ($query) => $this->filterByColumn($query, 'department_id', $request->query('department')))
            ->when($request->filled('role'), fn ($query) => $this->filterByColumn($query, 'role_id', $request->query('role')))
            ->when($request->filled('manager'), fn ($query) => $this->filterByColumn($query, 'manager_id', $request->query('manager')))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $request->query('status') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $managerFilter = $request->query('manager');

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'managerFilterLabel' => match (true) {
                $managerFilter === 'none' => 'No reporting manager',
                is_numeric($managerFilter) => ($manager = User::find($managerFilter)) ? "Reporting to {$manager->name}" : null,
                default => null,
            },
        ]);
    }

    /**
     * Filter by a foreign key, where the value "none" means the column is empty.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function filterByColumn(Builder $query, string $column, mixed $value): Builder
    {
        return $value === 'none' ? $query->whereNull($column) : $query->where($column, (int) $value);
    }

    public function create(Request $request): View
    {
        return view('admin.employees.create', [
            'employee' => new User(['is_active' => true]),
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Download an Excel template for the bulk employee importer.
     */
    public function downloadImportSample(Request $request): Response
    {
        $roles = Role::query()
            ->unless($request->user()->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', Role::SUPER_ADMIN_SLUG))
            ->orderBy('name')
            ->pluck('name')
            ->all();
        $sampleRole = in_array('Employee', $roles, true) ? 'Employee' : ($roles[0] ?? '');
        $sample = [
            'EMP1001',
            'Neha Verma',
            'neha@example.com',
            'Senior Developer',
            'Engineering',
            $sampleRole,
            'EMP1000',
            '2026-01-15',
            '1',
        ];
        $workbook = EmployeeImportSpreadsheet::createSample(self::IMPORT_HEADERS, $sample, $roles);

        return response($workbook, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="employee-import-sample.xlsx"',
            'Content-Length' => (string) strlen($workbook),
        ]);
    }

    /**
     * Import employees from an Excel or CSV file and mail each new account's login details.
     */
    public function import(ImportEmployeesRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            try {
                $tableRows = EmployeeImportSpreadsheet::read($file->getRealPath());
            } catch (RuntimeException $exception) {
                return back()->withErrors(['file' => $exception->getMessage()]);
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');

            if ($handle === false) {
                return back()->withErrors(['file' => 'The CSV file could not be read.']);
            }

            $tableRows = [];

            while (($values = fgetcsv($handle)) !== false) {
                $tableRows[] = $values;
            }

            fclose($handle);
        }

        $header = array_shift($tableRows);

        if (! is_array($header)) {
            return back()->withErrors(['file' => 'The uploaded file is empty.']);
        }

        $header = array_map(function ($column) {
            $column = trim((string) $column);
            $column = preg_replace('/^\xEF\xBB\xBF/', '', $column);

            return strtolower(str_replace([' ', '-'], '_', $column));
        }, $header);

        $missingHeaders = array_values(array_diff(self::IMPORT_HEADERS, $header));

        if ($missingHeaders !== []) {
            return back()->withErrors(['file' => 'Missing columns: '.implode(', ', $missingHeaders).'.']);
        }

        $departments = Department::query()->get(['id', 'name'])->keyBy(fn (Department $department) => strtolower(trim($department->name)));
        $roles = Role::query()
            ->unless($actor->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', Role::SUPER_ADMIN_SLUG))
            ->get(['id', 'name', 'slug'])
            ->keyBy(fn (Role $role) => strtolower(trim($role->name)));
        $existingUsersByCode = User::query()->pluck('id', 'employee_code')
            ->mapWithKeys(fn ($id, $code) => [strtolower(trim($code)) => (int) $id])
            ->all();
        $existingUsersByEmail = User::query()->pluck('id', 'email')
            ->mapWithKeys(fn ($id, $email) => [strtolower(trim($email)) => (int) $id])
            ->all();

        $rows = [];
        $rowErrors = [];
        $seenCodes = [];
        $seenEmails = [];
        $rowNumber = 1;

        foreach ($tableRows as $values) {
            $rowNumber++;

            if ($values === [] || collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $row = [];

            foreach (self::IMPORT_HEADERS as $headerName) {
                $index = array_search($headerName, $header, true);
                $row[$headerName] = $index !== false ? trim((string) ($values[$index] ?? '')) : '';
            }

            $rowPrefix = "Row {$rowNumber}";
            $errorsForRow = [];
            $codeKey = strtolower($row['employee_code']);
            $emailKey = strtolower($row['email']);

            if ($row['employee_code'] === '') {
                $errorsForRow[] = "{$rowPrefix}: Employee ID is required.";
            } elseif (strlen($row['employee_code']) > 30) {
                $errorsForRow[] = "{$rowPrefix}: Employee ID must be 30 characters or less.";
            } elseif (isset($seenCodes[$codeKey]) || isset($existingUsersByCode[$codeKey])) {
                $errorsForRow[] = "{$rowPrefix}: Employee ID {$row['employee_code']} already exists or is duplicated in the file.";
            }

            if ($row['name'] === '') {
                $errorsForRow[] = "{$rowPrefix}: Name is required.";
            } elseif (strlen($row['name']) > 255) {
                $errorsForRow[] = "{$rowPrefix}: Name must be 255 characters or less.";
            }

            if ($row['email'] === '') {
                $errorsForRow[] = "{$rowPrefix}: Email is required.";
            } elseif (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errorsForRow[] = "{$rowPrefix}: Email {$row['email']} is invalid.";
            } elseif (isset($seenEmails[$emailKey]) || isset($existingUsersByEmail[$emailKey])) {
                $errorsForRow[] = "{$rowPrefix}: Email {$row['email']} already exists or is duplicated in the file.";
            }

            $department = $row['department'] === '' ? null : ($departments[strtolower($row['department'])] ?? null);

            if ($row['department'] !== '' && $department === null) {
                $errorsForRow[] = "{$rowPrefix}: Department {$row['department']} does not exist.";
            }

            $role = $row['role'] === '' ? null : ($roles[strtolower($row['role'])] ?? null);

            if ($row['role'] === '') {
                $errorsForRow[] = "{$rowPrefix}: Role is required.";
            } elseif ($role === null) {
                $errorsForRow[] = "{$rowPrefix}: Role {$row['role']} does not exist or is not assignable.";
            }

            $joiningDate = null;

            if ($row['joining_date'] !== '') {
                $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['joining_date']);
                $dateErrors = \DateTimeImmutable::getLastErrors();

                if (! $parsedDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $parsedDate->format('Y-m-d') !== $row['joining_date']) {
                    $errorsForRow[] = "{$rowPrefix}: Joining date must use YYYY-MM-DD format.";
                } else {
                    $joiningDate = $parsedDate->format('Y-m-d');
                }
            }

            $activeValue = strtolower($row['is_active']);
            $isActive = in_array($activeValue, ['1', 'yes', 'true', 'active'], true);

            if ($activeValue !== '' && ! in_array($activeValue, ['0', '1', 'yes', 'no', 'true', 'false', 'active', 'inactive'], true)) {
                $errorsForRow[] = "{$rowPrefix}: is_active must be 1/0, yes/no or active/inactive.";
            }

            if ($row['manager_employee_code'] !== '' && strtolower($row['manager_employee_code']) === $codeKey) {
                $errorsForRow[] = "{$rowPrefix}: An employee cannot report to themselves.";
            }

            if ($errorsForRow !== []) {
                array_push($rowErrors, ...$errorsForRow);

                continue;
            }

            $seenCodes[$codeKey] = true;
            $seenEmails[$emailKey] = true;

            $rows[$codeKey] = [
                ...$row,
                'row_number' => $rowNumber,
                'department_id' => $department?->id,
                'role_id' => $role?->id,
                'joining_date' => $joiningDate,
                'is_active' => $row['is_active'] === '' ? true : $isActive,
            ];
        }

        $rows = $this->dropRowsWithInvalidManagers($rows, $existingUsersByCode, $rowErrors);

        if ($rows === []) {
            return back()->withErrors(['file' => $rowErrors ?: ['The uploaded file has no employee rows.']])->withInput();
        }

        $importedCount = 0;

        try {
            DB::transaction(function () use ($rows, &$importedCount) {
                $importedUsersByCode = [];

                foreach ($rows as $row) {
                    $plainPassword = User::generatePassword();
                    $employee = User::create([
                        'employee_code' => $row['employee_code'],
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'designation' => $row['designation'] ?: null,
                        'department_id' => $row['department_id'],
                        'role_id' => $row['role_id'],
                        'manager_id' => null,
                        'joining_date' => $row['joining_date'],
                        'is_active' => $row['is_active'],
                        'password' => $plainPassword,
                    ]);

                    Mail::send(new EmployeeCredentialsMail($employee, $plainPassword));
                    $importedUsersByCode[strtolower($employee->employee_code)] = $employee;
                    $importedCount++;
                }

                foreach ($rows as $row) {
                    $managerCode = strtolower($row['manager_employee_code']);

                    if ($managerCode === '') {
                        continue;
                    }

                    $manager = $importedUsersByCode[$managerCode]
                        ?? User::query()->whereRaw('LOWER(employee_code) = ?', [$managerCode])->first();

                    if ($manager !== null) {
                        $importedUsersByCode[strtolower($row['employee_code'])]->update(['manager_id' => $manager->id]);
                    }
                }
            });
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors([
                'file' => "Employee import stopped after {$importedCount} employees. Login mail delivery failed; check mail settings and try again.",
            ]);
        }

        $redirect = redirect()
            ->route('admin.employees.index')
            ->with('status', "{$importedCount} ".Str::plural('employee', $importedCount).' imported and login credentials mailed.'
                .($rowErrors === [] ? '' : ' Rows with errors were skipped.'));

        return $rowErrors === [] ? $redirect : $redirect->withErrors(['file' => $rowErrors]);
    }

    /**
     * Remove rows whose reporting manager is missing (or was itself skipped) or forms a cycle,
     * repeating until every remaining row points at an importable or existing manager.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, int>  $existingUsersByCode
     * @param  list<string>  $rowErrors
     * @return array<string, array<string, mixed>>
     */
    private function dropRowsWithInvalidManagers(array $rows, array $existingUsersByCode, array &$rowErrors): array
    {
        do {
            $removedAny = false;

            foreach ($rows as $codeKey => $row) {
                $managerCode = strtolower($row['manager_employee_code']);

                if ($managerCode !== '' && ! isset($rows[$managerCode]) && ! isset($existingUsersByCode[$managerCode])) {
                    $rowErrors[] = "Row {$row['row_number']}: Reporting manager {$row['manager_employee_code']} was not found.";
                    unset($rows[$codeKey]);
                    $removedAny = true;
                }
            }
        } while ($removedAny);

        foreach (array_keys($rows) as $startCode) {
            if (! isset($rows[$startCode])) {
                continue;
            }

            $visitedCodes = [$startCode];
            $currentCode = strtolower($rows[$startCode]['manager_employee_code']);

            while ($currentCode !== '' && isset($rows[$currentCode])) {
                if (in_array($currentCode, $visitedCodes, true)) {
                    foreach (array_slice($visitedCodes, array_search($currentCode, $visitedCodes, true)) as $cycleCode) {
                        $rowErrors[] = "Row {$rows[$cycleCode]['row_number']}: Reporting manager hierarchy contains a cycle.";
                        unset($rows[$cycleCode]);
                    }

                    return $this->dropRowsWithInvalidManagers($rows, $existingUsersByCode, $rowErrors);
                }

                $visitedCodes[] = $currentCode;
                $currentCode = strtolower($rows[$currentCode]['manager_employee_code']);
            }
        }

        return $rows;
    }

    /**
     * Create the employee with a random password and mail them their login details.
     * If the mail cannot be sent, the employee is not created.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        try {
            $employee = DB::transaction(function () use ($request) {
                $plainPassword = User::generatePassword();

                $employee = User::create([
                    ...$request->validated(),
                    'password' => $plainPassword,
                    'is_active' => $request->boolean('is_active'),
                ]);

                Mail::send(new EmployeeCredentialsMail($employee, $plainPassword));

                return $employee;
            });
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withInput()->withErrors(['employee' => self::MAIL_FAILED_MESSAGE.' The employee was not added.']);
        }

        return redirect()
            ->route('admin.employees.index')
            ->with('status', "Employee added. Login details were mailed to {$employee->email}.");
    }

    /**
     * Replace the employee's password with a new random one and mail it to them.
     */
    public function sendCredentials(Request $request, User $employee): RedirectResponse
    {
        $this->ensureCanModify($request->user(), $employee);

        if ($request->user()->is($employee)) {
            return back()->withErrors(['employee' => 'Change your own password from My profile.']);
        }

        try {
            DB::transaction(function () use ($employee) {
                $plainPassword = User::generatePassword();
                $employee->update(['password' => $plainPassword]);

                Mail::send(new EmployeeCredentialsMail($employee, $plainPassword));
            });
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['employee' => self::MAIL_FAILED_MESSAGE.' The old password still works.']);
        }

        return back()->with('status', "New login details were mailed to {$employee->email}.");
    }

    public function edit(Request $request, User $employee): View
    {
        $this->ensureCanModify($request->user(), $employee);

        return view('admin.employees.edit', [
            'employee' => $employee,
            ...$this->formOptions($request->user(), $employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): RedirectResponse
    {
        $data = $request->validated();

        $isEditingSelf = $request->user()->is($employee);

        if ($isEditingSelf) {
            unset($data['role_id']);
        }

        $employee->update([...$data, 'is_active' => $isEditingSelf || $request->boolean('is_active')]);

        return redirect()->route('admin.employees.index')->with('status', 'Employee updated.');
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        $this->ensureCanModify($request->user(), $employee);

        if ($request->user()->is($employee)) {
            return back()->withErrors(['employee' => 'You cannot delete your own account.']);
        }

        $employee->delete();

        return redirect()->route('admin.employees.index')->with('status', 'Employee deleted.');
    }

    /**
     * Only a super admin may change another super admin's account.
     */
    private function ensureCanModify(User $actor, User $employee): void
    {
        abort_if($employee->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
    }

    /**
     * @return array{departments: Collection<int, Department>, roles: Collection<int, Role>, managers: Collection<int, User>}
     */
    private function formOptions(User $actor, ?User $employee = null): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::query()
                ->unless($actor->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', Role::SUPER_ADMIN_SLUG))
                ->orderBy('name')
                ->get(),
            'managers' => User::query()
                ->where('is_active', true)
                ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code']),
        ];
    }
}
