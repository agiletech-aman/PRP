<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\PermissionName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'employee_code', 'name', 'designation', 'email', 'password', 'role_id',
    'department_id', 'manager_id', 'joining_date', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joining_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The reporting manager who rates this employee.
     *
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /**
     * Appraisals in which this user is the employee being appraised.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'employee_id');
    }

    /**
     * Appraisals in which this user is the reviewing manager.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function managedAppraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'manager_id');
    }

    /**
     * Whether the user takes part in any appraisal, as the employee or as the manager.
     */
    public function hasAppraisals(): bool
    {
        return once(fn (): bool => Appraisal::query()->involving($this)->exists());
    }

    /**
     * A random 10-character login password that is easy to type: letters and digits, no look-alike characters.
     */
    public static function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

        return collect(range(1, 10))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }

    /**
     * Up to two capital letters from the user's name, shown in avatars.
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    public function hasPermission(PermissionName $permission): bool
    {
        return $this->role?->hasPermission($permission) ?? false;
    }

    /**
     * Whether the user may sign in to the portal at all: they need a permission,
     * or an appraisal to fill in as the employee or the manager.
     */
    public function canAccessPanel(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $hasAnyPermission = $this->role && ($this->role->isSuperAdmin() || $this->role->permissions->isNotEmpty());

        return $hasAnyPermission || $this->hasAppraisals();
    }

    /**
     * The first admin page this user is allowed to open after signing in.
     */
    public function homeUrl(): string
    {
        return match (true) {
            $this->can(PermissionName::ViewDashboard->value) => route('admin.dashboard'),
            $this->can(PermissionName::ViewEmployees->value) => route('admin.employees.index'),
            $this->can(PermissionName::ManageDepartments->value) => route('admin.departments.index'),
            $this->can(PermissionName::ManageRatings->value) => route('admin.categories.index'),
            $this->can(PermissionName::ManageRoles->value) => route('admin.roles.index'),
            $this->can(PermissionName::SendAppraisals->value) => route('admin.appraisals.index'),
            $this->hasAppraisals() => route('admin.appraisals.mine'),
            default => route('admin.no-access'),
        };
    }
}
