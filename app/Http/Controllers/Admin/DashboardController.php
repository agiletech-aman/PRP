<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RatingCategory;
use App\Models\RatingFactor;
use App\Models\Role;
use App\Models\User;
use App\PermissionName;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Number of months shown in the joining trend chart.
     */
    private const TREND_MONTHS = 12;

    /**
     * People who manage employees see the whole organisation; everyone else sees their own team.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return $user->can(PermissionName::ManageEmployees->value)
            ? $this->organisationDashboard()
            : $this->teamDashboard($user);
    }

    private function organisationDashboard(): View
    {
        $employees = User::query()->get(['id', 'role_id', 'department_id', 'manager_id', 'is_active', 'joining_date']);
        $employeeCount = $employees->count();
        $superAdminRoleId = Role::where('slug', Role::SUPER_ADMIN_SLUG)->value('id');
        $nonAdmins = $employees->where('role_id', '!=', $superAdminRoleId);

        $departments = Department::query()
            ->withCount(['users', 'ratingCategories'])
            ->orderByDesc('users_count')
            ->orderBy('name')
            ->get();

        $departmentsWithoutKra = $departments->where('rating_categories_count', 0)->count();
        $headingsWithoutFactors = RatingCategory::doesntHave('factors')->count();

        return view('admin.dashboard.organisation', [
            'greeting' => $this->greeting(),
            'employeeCount' => $employeeCount,
            'activeEmployeeCount' => $employees->where('is_active', true)->count(),
            'joinedLast30Days' => $employees->filter(fn (User $e) => $e->joining_date?->isAfter(now()->subDays(30)))->count(),
            'departmentCount' => $departments->count(),
            'departmentsWithoutKra' => $departmentsWithoutKra,
            'commonCategoryCount' => RatingCategory::whereNull('department_id')->count(),
            'kraCategoryCount' => RatingCategory::whereNotNull('department_id')->count(),
            'factorCount' => RatingFactor::count(),
            'activeFactorCount' => RatingFactor::where('is_active', true)->count(),
            'departments' => $departments,
            'largestDepartmentSize' => max(1, (int) $departments->max('users_count')),
            'roles' => Role::query()->withCount('users')->orderByDesc('users_count')->orderBy('name')->get(),
            'recentEmployees' => User::query()->with('department')->latest('id')->limit(5)->get(),
            'joiningTrend' => $this->joiningTrend($employees),
            'attentionItems' => array_values(array_filter([
                $this->attentionItem('Employees without a reporting manager', $nonAdmins->whereNull('manager_id')->count(), route('admin.employees.index', ['manager' => 'none'])),
                $this->attentionItem('Employees without a department', $nonAdmins->whereNull('department_id')->count(), route('admin.employees.index', ['department' => 'none'])),
                $this->attentionItem('Employees without a role', $employees->whereNull('role_id')->count(), route('admin.employees.index', ['role' => 'none'])),
                $this->attentionItem('Inactive accounts', $employees->where('is_active', false)->count(), route('admin.employees.index', ['status' => 'inactive'])),
                $this->attentionItem('Departments without a KRA heading', $departmentsWithoutKra, route('admin.departments.index'), PermissionName::ManageDepartments),
                $this->attentionItem('Rating headings without factors', $headingsWithoutFactors, route('admin.categories.index'), PermissionName::ManageRatings),
            ])),
        ]);
    }

    private function teamDashboard(User $user): View
    {
        $user->load(['department', 'manager', 'role']);

        $team = $user->directReports()->with('department')->orderBy('name')->get();
        $teamSize = $team->count();

        $designationBreakdown = $team
            ->groupBy(fn (User $member) => $member->designation ?: 'No designation')
            ->map(fn (Collection $members, string $designation) => [
                'designation' => $designation,
                'count' => $members->count(),
                'share' => $teamSize > 0 ? (int) round($members->count() / $teamSize * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values();

        $averageTenureYears = $team->whereNotNull('joining_date')->isNotEmpty()
            ? round($team->whereNotNull('joining_date')->avg(fn (User $member) => $member->joining_date->diffInDays(now()) / 365), 1)
            : null;

        return view('admin.dashboard.team', [
            'greeting' => $this->greeting(),
            'user' => $user,
            'team' => $team,
            'teamSize' => $teamSize,
            'activeTeamCount' => $team->where('is_active', true)->count(),
            'recentlyJoinedCount' => $team->filter(fn (User $member) => $member->joining_date?->isAfter(now()->subYear()))->count(),
            'averageTenureYears' => $averageTenureYears,
            'designationBreakdown' => $designationBreakdown,
            'joiningTrend' => $this->joiningTrend($team),
            'formHeadingCount' => $user->department
                ? $user->department->appraisalFormCategories()->count()
                : RatingCategory::whereNull('department_id')->where('is_active', true)->count(),
        ]);
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Joiners per month for the last TREND_MONTHS months, oldest first.
     *
     * @param  Collection<int, User>  $people
     * @return list<array{label: string, tooltip: string, count: int, height: int}>
     */
    private function joiningTrend(Collection $people): array
    {
        $months = collect(range(self::TREND_MONTHS - 1, 0))
            ->map(fn (int $monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo));

        $counts = $months->map(fn (Carbon $month) => $people
            ->filter(fn (User $person) => $person->joining_date?->isSameMonth($month))
            ->count());

        $peak = max(1, $counts->max());

        return $months->map(fn (Carbon $month, int $index) => [
            'label' => $month->format('M'),
            'tooltip' => $month->format('F Y').': '.$counts[$index].' joined',
            'count' => $counts[$index],
            'height' => (int) round($counts[$index] / $peak * 100),
        ])->all();
    }

    /**
     * @return array{label: string, count: int, url: ?string}|null
     */
    private function attentionItem(string $label, int $count, string $url, PermissionName $requiredPermission = PermissionName::ViewEmployees): ?array
    {
        if ($count === 0) {
            return null;
        }

        return [
            'label' => $label,
            'count' => $count,
            'url' => request()->user()->can($requiredPermission->value) ? $url : null,
        ];
    }
}
