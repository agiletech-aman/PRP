<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Categories that only belong to this department (e.g. KRA).
     *
     * @return HasMany<RatingCategory, $this>
     */
    public function ratingCategories(): HasMany
    {
        return $this->hasMany(RatingCategory::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Every active category shown on this department's appraisal form:
     * common categories followed by department-specific ones, with their active factors.
     *
     * @return Builder<RatingCategory>
     */
    public function appraisalFormCategories(): Builder
    {
        return RatingCategory::query()
            ->forDepartment($this)
            ->where('is_active', true)
            ->with(['factors' => fn ($query) => $query->where('is_active', true)])
            ->orderByRaw('department_id is not null')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
