<?php

namespace App\Models;

use Database\Factories\RatingFactorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rating_category_id', 'name', 'sort_order', 'is_active'])]
class RatingFactor extends Model
{
    /** @use HasFactory<RatingFactorFactory> */
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
     * @return BelongsTo<RatingCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RatingCategory::class, 'rating_category_id');
    }
}
