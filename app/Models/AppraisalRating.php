<?php

namespace App\Models;

use Database\Factories\AppraisalRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appraisal_id', 'rating_factor_id', 'category_code', 'category_name', 'factor_name',
    'sort_order', 'employee_rating', 'manager_rating',
])]
class AppraisalRating extends Model
{
    /** @use HasFactory<AppraisalRatingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'employee_rating' => 'integer',
            'manager_rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * @return BelongsTo<RatingFactor, $this>
     */
    public function factor(): BelongsTo
    {
        return $this->belongsTo(RatingFactor::class, 'rating_factor_id');
    }
}
