<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\PermissionName;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP'.fake()->unique()->numberBetween(1000, 99999),
            'name' => fake()->name(),
            'designation' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => null,
            'joining_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user holds the built-in Admin role (every permission).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(
                ['slug' => Role::SUPER_ADMIN_SLUG],
                ['name' => 'Admin', 'is_system' => true],
            )->id,
        ]);
    }

    /**
     * Give the user a fresh role that holds exactly the given permissions.
     */
    public function withPermissions(PermissionName ...$permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::factory()->withPermissions(...$permissions),
        ]);
    }

    /**
     * Indicate that the user is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
