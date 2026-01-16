<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Computer Science',
                'code' => 'CS',
                'description' => 'Department of Computer Science and Engineering',
            ],
            [
                'name' => 'Electrical Engineering',
                'code' => 'EE',
                'description' => 'Department of Electrical Engineering',
            ],
            [
                'name' => 'Mechanical Engineering',
                'code' => 'ME',
                'description' => 'Department of Mechanical Engineering',
            ],
            [
                'name' => 'Civil Engineering',
                'code' => 'CE',
                'description' => 'Department of Civil Engineering',
            ],
            [
                'name' => 'Business Administration',
                'code' => 'BA',
                'description' => 'Department of Business Administration',
            ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }

        $this->command->info('Departments seeded successfully!');
    }
}