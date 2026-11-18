<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RatingCategory;
use Illuminate\Database\Seeder;

class AppraisalStructureSeeder extends Seeder
{
    /**
     * Rating headings shared by every department.
     *
     * @var array<string, array{name: string, factors: list<string>}>
     */
    private const COMMON_CATEGORIES = [
        'A' => ['name' => 'Approach to work', 'factors' => [
            'Follows instruction', 'Proactive approach', 'Planning & Organizing',
            'Accepts constructive criticism', 'Flexible & adaptable',
        ]],
        'B' => ['name' => 'Technical skills', 'factors' => [
            'Job Knowledge', 'Application of skills', 'Analyzing the problem',
            'Follows proper procedures', 'Follows Standards', 'Learning New Skills',
        ]],
        'C' => ['name' => 'Quality of work', 'factors' => [
            'Accuracy', 'Presentation', 'Reliability', 'Errorless work', 'Follow-through and Follow-up',
        ]],
        'D' => ['name' => 'Handling targets and dead lines', 'factors' => [
            'Completion of work on-time', 'Ability to work under pressure', 'Priority setting',
        ]],
        'E' => ['name' => 'Interpersonal Skills', 'factors' => [
            'Relationship with colleagues', 'Co-operation', 'Co-ordination',
            'Team work', 'Problem-solving', 'Decision making',
        ]],
        'F' => ['name' => 'Communication Skills', 'factors' => [
            'Oral & Written expression', 'Speaking in English',
            'Shares information / knowledge willingly', 'Reporting',
        ]],
        'G' => ['name' => 'Willingness to learn and develop skills', 'factors' => [
            'Seeks training and development', 'Open to ideas',
        ]],
        'H' => ['name' => 'Personality', 'factors' => [
            'Enthusiastic, Fair and mature', 'Trustworthy', 'Volunteer in Internal Activity',
        ]],
        'I' => ['name' => 'Code of conduct', 'factors' => [
            'Work place etiquette', 'Attendance', 'Punctuality', 'Email & mobile phone etiquette',
            'Dress Code', 'Adhering company policies and procedures', 'Following instructions and protocols',
        ]],
        'J' => ['name' => 'Leadership Skills', 'factors' => [
            'Coach and Develop Others', 'Team Building', 'Business knowledge',
            'New Strategy and Direction', 'Client interaction & Co-ordination', 'Client Replies',
        ]],
    ];

    /**
     * Starting KRA factors copied into each department; admins edit them per department.
     *
     * @var list<string>
     */
    private const DEFAULT_KRA_FACTORS = [
        'Operation ( Revenue Targets)',
        'Role and responsibility (Action Plan Targets)',
        'Support (Key Deliverables)',
    ];

    /**
     * @var list<string>
     */
    private const DEPARTMENTS = ['Development', 'Sales', 'Human Resources', 'Accounts', 'Support'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sortOrder = 1;

        foreach (self::COMMON_CATEGORIES as $code => $definition) {
            $this->createCategory(null, $code, $definition['name'], $definition['factors'], $sortOrder++);
        }

        foreach (self::DEPARTMENTS as $departmentName) {
            $department = Department::firstOrCreate(['name' => $departmentName]);

            $this->createCategory($department->id, 'K', 'Role Based KRA Achievement', self::DEFAULT_KRA_FACTORS, 1);
        }
    }

    /**
     * @param  list<string>  $factorNames
     */
    private function createCategory(?int $departmentId, string $code, string $name, array $factorNames, int $sortOrder): void
    {
        $category = RatingCategory::firstOrCreate(
            ['department_id' => $departmentId, 'code' => $code],
            ['name' => $name, 'sort_order' => $sortOrder],
        );

        if ($category->factors()->exists()) {
            return;
        }

        foreach ($factorNames as $index => $factorName) {
            $category->factors()->create(['name' => $factorName, 'sort_order' => $index + 1]);
        }
    }
}
