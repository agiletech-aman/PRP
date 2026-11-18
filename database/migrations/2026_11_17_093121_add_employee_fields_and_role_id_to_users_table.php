<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base roles that existing users' string `role` column is mapped onto.
     *
     * @var array<string, array{name: string, is_system: bool}>
     */
    private const BASE_ROLES = [
        'admin' => ['name' => 'Admin', 'is_system' => true],
        'manager' => ['name' => 'Manager', 'is_system' => false],
        'employee' => ['name' => 'Employee', 'is_system' => false],
    ];

    /**
     * Run the migrations.
     *
     * Replaces the string `role` column with a `role_id` foreign key and adds employee profile fields.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->string('designation')->nullable()->after('name');
            $table->date('joining_date')->nullable()->after('manager_id');
            $table->boolean('is_active')->default(true)->after('joining_date');
        });

        foreach (self::BASE_ROLES as $slug => $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role['name'],
                'slug' => $slug,
                'is_system' => $role['is_system'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            DB::table('users')->where('role', $slug)->update(['role_id' => $roleId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('employee')->after('password');
        });

        foreach (array_keys(self::BASE_ROLES) as $slug) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');

            if ($roleId) {
                DB::table('users')->where('role_id', $roleId)->update(['role' => $slug]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['designation', 'joining_date', 'is_active']);
        });
    }
};
