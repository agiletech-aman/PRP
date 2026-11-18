<?php

namespace Database\Factories;

use App\AppraisalStatus;
use App\Models\Appraisal;
use App\Models\AppraisalRating;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appraisal>
 */
class AppraisalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Annual Appraisal '.now()->year,
            'employee_id' => User::factory(),
            'manager_id' => User::factory(),
            'department_id' => Department::factory(),
            'sent_by' => null,
            'status' => AppraisalStatus::Pending,
            'sent_at' => now(),
        ];
    }

    /**
     * Give the appraisal a number of unrated factor rows.
     */
    public function withRatings(int $count = 3): static
    {
        return $this->has(AppraisalRating::factory()->count($count), 'ratings');
    }

    /**
     * Indicate that the employee has submitted their self-rating.
     */
    public function employeeSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppraisalStatus::EmployeeSubmitted,
            'employee_submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that both the employee and the manager have submitted their ratings.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppraisalStatus::Completed,
            'employee_submitted_at' => now()->subDay(),
            'manager_submitted_at' => now(),
        ]);
    }
}
