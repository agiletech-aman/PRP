<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'employee_code' => 'ADMIN001',
                'name' => 'Administrator',
                'designation' => 'System Administrator',
                'password' => 'admin123',
                'role_id' => Role::where('slug', Role::SUPER_ADMIN_SLUG)->value('id'),
                'email_verified_at' => now(),
            ],
        );

        $this->call(AppraisalStructureSeeder::class);
    }
}
