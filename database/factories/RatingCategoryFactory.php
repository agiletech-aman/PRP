<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\RatingCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingCategory>
 */
class RatingCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => null,
            'code' => fake()->randomLetter(),
            'name' => fake()->words(3, true),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the category belongs only to the given department.
     */
    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
