<?php

namespace App\Models;

use App\AppraisalStatus;
use App\PermissionName;
use Database\Factories\AppraisalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'title', 'employee_id', 'manager_id', 'department_id', 'sent_by', 'status',
    'employee_comments', 'manager_comments', 'hr_remarks', 'hr_remarks_by', 'sent_at',
    'employee_submitted_at', 'manager_submitted_at', 'hr_submitted_at', 'hr_comments', 'hr_remarked_at',
])]
class Appraisal extends Model
{
    /** @use HasFactory<AppraisalFactory> */
    use HasFactory;

    /**
     * The highest rating a factor can receive.
     */
    public const MAX_RATING = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppraisalStatus::class,
            'sent_at' => 'datetime',
            'employee_submitted_at' => 'datetime',
            'manager_submitted_at' => 'datetime',
            'hr_submitted_at' => 'datetime',
            'hr_remarked_at' => 'datetime',
        ];
    }

    /**
     * Create an appraisal for the employee, copying their department's current form into it.
     */
    public static function startFor(User $employee, string $title, User $sender): self
    {
        return DB::transaction(function () use ($employee, $title, $sender) {
            $appraisal = self::create([
                'title' => $title,
                'employee_id' => $employee->id,
                'manager_id' => $employee->manager_id,
                'department_id' => $employee->department_id,
                'sent_by' => $sender->id,
                'status' => AppraisalStatus::Pending,
                'sent_at' => now(),
            ]);

            $sortOrder = 0;

            foreach ($employee->department->appraisalFormCategories()->get() as $category) {
                foreach ($category->factors as $factor) {
                    $appraisal->ratings()->create([
                        'rating_factor_id' => $factor->id,
                        'category_code' => $category->code,
                        'category_name' => $category->name,
                        'factor_name' => $factor->name,
                        'sort_order' => ++$sortOrder,
                    ]);
                }
            }

            return $appraisal;
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * The HR user who last saved the HR remarks.
     *
     * @return BelongsTo<User, $this>
     */
    public function hrRemarksAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_remarks_by');
    }

    /**
     * @return HasMany<AppraisalRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(AppraisalRating::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Appraisals the user takes part in, either as the employee or as the manager.
     *
     * @param  Builder<Appraisal>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('employee_id', $user->id)->orWhere('manager_id', $user->id);
        });
    }

    /**
     * Appraisals that have not been completed yet.
     *
     * @param  Builder<Appraisal>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', '!=', AppraisalStatus::Completed);
    }

    /**
     * Which rating column the user fills in right now: "employee", "manager", or null when it is not their turn.
     */
    public function ratingStageFor(User $user): ?string
    {
        return match (true) {
            $this->status === AppraisalStatus::Pending && $user->id === $this->employee_id => 'employee',
            $this->status === AppraisalStatus::EmployeeSubmitted && $user->id === $this->manager_id => 'manager',
            $this->status === AppraisalStatus::AwaitingHr
                && ($user->can(PermissionName::EvaluateAppraisals->value) || $user->isSuperAdmin()) => 'hr',
            default => null,
        };
    }

    /**
     * How the user takes part in this appraisal: "employee", "manager", "hr" (sends appraisals), or null.
     */
    public function participantRoleFor(User $user): ?string
    {
        return match (true) {
            $user->id === $this->employee_id => 'employee',
            $user->id === $this->manager_id => 'manager',
            ($user->can(PermissionName::EvaluateAppraisals->value) || $user->isSuperAdmin()) => 'evaluator',
            $user->can(PermissionName::SendAppraisals->value) => 'hr',
            $user->managesInHierarchy($this->employee) => 'hierarchy',
            default => null,
        };
    }

    /**
     * Save the user's ratings for their stage and move the appraisal to the next status.
     *
     * @param  array<int|string, int|string>  $ratingsById  Rating value keyed by appraisal_rating id.
     */
    public function submitRatings(string $stage, array $ratingsById, ?string $comments): void
    {
        DB::transaction(function () use ($stage, $ratingsById, $comments) {
            foreach ($this->ratings as $rating) {
                $rating->update(["{$stage}_rating" => (int) $ratingsById[$rating->id]]);
            }

            $this->update([
                "{$stage}_comments" => $comments,
                "{$stage}_submitted_at" => now(),
                'status' => match ($stage) {
                    'employee' => AppraisalStatus::EmployeeSubmitted,
                    'manager' => AppraisalStatus::AwaitingHr,
                    'hr' => AppraisalStatus::Completed,
                },
            ]);
        });
    }

    /**
     * Average of the filled-in ratings in the given column ("employee" or "manager"), or null if none.
     */
    public function averageRating(string $stage): ?float
    {
        $average = $this->ratings->whereNotNull("{$stage}_rating")->avg("{$stage}_rating");

        return $average === null ? null : round($average, 2);
    }
}
