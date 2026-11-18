<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
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

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        User::create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.employees.index')->with('status', 'Employee added.');
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

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

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
