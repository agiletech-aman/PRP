<?php

namespace App\Models;

use Database\Factories\RatingCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['department_id', 'code', 'name', 'sort_order', 'is_active'])]
class RatingCategory extends Model
{
    /** @use HasFactory<RatingCategoryFactory> */
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
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<RatingFactor, $this>
     */
    public function factors(): HasMany
    {
        return $this->hasMany(RatingFactor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isCommon(): bool
    {
        return $this->department_id === null;
    }

    /**
     * Common categories plus the ones specific to the given department.
     *
     * @param  Builder<RatingCategory>  $query
     */
    #[Scope]
    protected function forDepartment(Builder $query, Department $department): void
    {
        $query->where(function (Builder $query) use ($department) {
            $query->whereNull('department_id')->orWhere('department_id', $department->id);
        });
    }
}
