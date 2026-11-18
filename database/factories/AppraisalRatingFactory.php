<?php

namespace Database\Factories;

use App\Models\Appraisal;
use App\Models\AppraisalRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppraisalRating>
 */
class AppraisalRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appraisal_id' => Appraisal::factory(),
            'rating_factor_id' => null,
            'category_code' => 'A',
            'category_name' => 'Job Knowledge',
            'factor_name' => fake()->sentence(3),
            'sort_order' => fake()->numberBetween(1, 20),
            'employee_rating' => null,
            'manager_rating' => null,
        ];
    }
}
