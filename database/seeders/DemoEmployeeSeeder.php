<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fills every department with a manager and a team of fake employees for demos.
 * All demo accounts use the password "password" and an @demo.test email address.
 */
class DemoEmployeeSeeder extends Seeder
{
    /**
     * Job titles used for team members, per department name.
     *
     * @var array<string, list<string>>
     */
    private const DESIGNATIONS = [
        'Development' => ['Software Engineer', 'Senior Software Engineer', 'Frontend Developer', 'QA Engineer', 'DevOps Engineer'],
        'Sales' => ['Sales Executive', 'Business Development Executive', 'Inside Sales Representative', 'Account Manager'],
        'Human Resources' => ['HR Executive', 'Recruiter', 'Payroll Executive'],
        'Accounts' => ['Accountant', 'Accounts Executive', 'Finance Analyst'],
        'Support' => ['Support Executive', 'Technical Support Engineer', 'Customer Success Associate'],
    ];

    private Generator $faker;

    private int $nextEmployeeNumber;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->faker = FakerFactory::create('en_IN');
        $this->nextEmployeeNumber = $this->firstFreeEmployeeNumber();

        $roles = Role::whereIn('slug', ['hr', 'manager', 'employee'])->pluck('id', 'slug');

        foreach (Department::orderBy('name')->get() as $department) {
            $designations = self::DESIGNATIONS[$department->name] ?? ['Executive', 'Senior Executive', 'Associate'];

            $manager = $this->createEmployee([
                'designation' => "{$department->name} Manager",
                'department_id' => $department->id,
                'role_id' => $roles['manager'] ?? null,
            ]);

            foreach (range(1, $this->faker->numberBetween(4, 6)) as $ignored) {
                $this->createEmployee([
                    'designation' => $this->faker->randomElement($designations),
                    'department_id' => $department->id,
                    'role_id' => $roles['employee'] ?? null,
                    'manager_id' => $manager->id,
                    'is_active' => $this->faker->boolean(90),
                ]);
            }

            if ($department->name === 'Human Resources') {
                $this->createEmployee([
                    'designation' => 'HR Business Partner',
                    'department_id' => $department->id,
                    'role_id' => $roles['hr'] ?? null,
                    'manager_id' => $manager->id,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEmployee(array $attributes): User
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $code = 'EMP'.$this->nextEmployeeNumber++;

        return User::factory()->create([
            'employee_code' => $code,
            'name' => "{$firstName} {$lastName}",
            'email' => Str::slug("{$firstName} {$lastName} {$code}", '.').'@demo.test',
            'joining_date' => $this->faker->dateTimeBetween('-6 years', '-2 months'),
            ...$attributes,
        ]);
    }

    private function firstFreeEmployeeNumber(): int
    {
        $highest = User::where('employee_code', 'like', 'EMP%')
            ->pluck('employee_code')
            ->map(fn (string $code) => (int) substr($code, 3))
            ->max();

        return max(1001, ($highest ?? 0) + 1);
    }
}
