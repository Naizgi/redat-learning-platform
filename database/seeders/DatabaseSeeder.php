<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed departments first
        $this->call(DepartmentSeeder::class);

        // Get the first department
        $department = Department::first();

        // ================= CREATE ADMIN USERS =================
        
        // Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@redat.edu',
            'password' => Hash::make('admin123'),
            'department_id' => $department->id,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // System Admin
        User::create([
            'name' => 'System Administrator',
            'email' => 'sysadmin@redat.edu',
            'password' => Hash::make('sysadmin123'),
            'department_id' => $department->id,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ================= CREATE STUDENT USERS =================
        
        // Test Student 1
        User::create([
            'name' => 'John Doe',
            'email' => 'student1@redat.edu',
            'password' => Hash::make('student123'),
            'department_id' => $department->id,
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Test Student 2
        User::create([
            'name' => 'Jane Smith',
            'email' => 'student2@redat.edu',
            'password' => Hash::make('student123'),
            'department_id' => $department->id,
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Test Student 3 (inactive)
        User::create([
            'name' => 'Bob Johnson',
            'email' => 'student3@redat.edu',
            'password' => Hash::make('student123'),
            'department_id' => $department->id,
            'role' => 'student',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        // Test Student 4 (not verified)
        User::create([
            'name' => 'Alice Williams',
            'email' => 'student4@redat.edu',
            'password' => Hash::make('student123'),
            'department_id' => $department->id,
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        // ================= CREATE TEST USER FOR DEVELOPMENT =================
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'department_id' => $department->id,
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ================= CREATE ADDITIONAL RANDOM USERS =================
        // Create 5 random admin users
        User::factory(5)
            ->admin()
            ->active()
            ->create();

        // Create 20 random student users
        User::factory(20)
            ->student()
            ->active()
            ->create();

        // Create 5 inactive students
        User::factory(5)
            ->student()
            ->inactive()
            ->create();

        $this->command->info('Database seeded successfully!');
        $this->command->info('==========================================');
        $this->command->info('Admin Users:');
        $this->command->info('1. Email: admin@redat.edu | Password: admin123');
        $this->command->info('2. Email: sysadmin@redat.edu | Password: sysadmin123');
        $this->command->info('');
        $this->command->info('Student Users:');
        $this->command->info('1. Email: student1@redat.edu | Password: student123 (Active)');
        $this->command->info('2. Email: student2@redat.edu | Password: student123 (Active)');
        $this->command->info('3. Email: student3@redat.edu | Password: student123 (Inactive)');
        $this->command->info('4. Email: student4@redat.edu | Password: student123 (Not Verified)');
        $this->command->info('5. Email: test@example.com | Password: password123 (Test User)');
        $this->command->info('==========================================');
    }

}