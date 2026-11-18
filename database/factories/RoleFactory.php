<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\PermissionName;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    /**
     * Attach the given permissions to the role after it is created.
     */
    public function withPermissions(PermissionName ...$permissions): static
    {
        return $this->afterCreating(function (Role $role) use ($permissions) {
            $permissionIds = collect($permissions)->map(fn (PermissionName $permission) => Permission::firstOrCreate(
                ['name' => $permission->value],
                ['label' => $permission->label(), 'group' => $permission->group()],
            )->id);

            $role->permissions()->sync($permissionIds);
        });
    }
}
