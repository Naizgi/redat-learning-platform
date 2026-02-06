<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Validation\Rule;
use App\Models\Material; // Add Material model
use App\Models\User; // Add User model
use App\Models\Course; // Add Course model

class DepartmentController extends Controller
{
    // List all departments with statistics
    public function index()
    {
        // Get all departments
        $departments = Department::all();
        
        // Transform departments to include statistics
        $departmentsWithStats = $departments->map(function ($department) {
            // Get statistics for this department
            $materialsCount = Material::where('department_id', $department->id)->count();
            $videosCount = Material::where('department_id', $department->id)
                ->where(function ($query) {
                    $query->where('type', 'video')
                        ->orWhere('file_type', 'video')
                        ->orWhere('file_name', 'like', '%.mp4')
                        ->orWhere('file_name', 'like', '%.avi')
                        ->orWhere('file_name', 'like', '%.mov')
                        ->orWhere('file_name', 'like', '%.wmv');
                })->count();
            $coursesCount = Course::where('department_id', $department->id)->count();
            $usersCount = User::where('department_id', $department->id)->count();
            
            return [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'slug' => $department->slug,
                'is_active' => $department->is_active ?? true,
                'order' => $department->order ?? 0,
                'color' => $department->color,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
                // Statistics
                'materials_count' => $materialsCount,
                'videos_count' => $videosCount,
                'courses_count' => $coursesCount,
                'users_count' => $usersCount,
                // For backward compatibility
                'total_materials' => $materialsCount,
                'total_videos' => $videosCount,
                'total_courses' => $coursesCount,
                'total_users' => $usersCount,
                // For frontend component
                'stats' => [
                    'materials' => $materialsCount,
                    'videos' => $videosCount,
                    'courses' => $coursesCount,
                    'users' => $usersCount,
                    'updated_at' => $department->updated_at
                ]
            ];
        });
        
        return response()->json([
            'departments' => $departmentsWithStats,
            'statistics' => [
                'total_departments' => $departments->count(),
                'total_materials' => $departmentsWithStats->sum('materials_count'),
                'total_videos' => $departmentsWithStats->sum('videos_count'),
                'total_courses' => $departmentsWithStats->sum('courses_count'),
                'total_users' => $departmentsWithStats->sum('users_count')
            ]
        ]);
    }

    // Show a single department with detailed statistics
    public function show(Department $department)
    {
        // Get detailed statistics for this department
        $materialsCount = Material::where('department_id', $department->id)->count();
        $videosCount = Material::where('department_id', $department->id)
            ->where(function ($query) {
                $query->where('type', 'video')
                    ->orWhere('file_type', 'video')
                    ->orWhere('file_name', 'like', '%.mp4')
                    ->orWhere('file_name', 'like', '%.avi')
                    ->orWhere('file_name', 'like', '%.mov')
                    ->orWhere('file_name', 'like', '%.wmv');
            })->count();
        $coursesCount = Course::where('department_id', $department->id)->count();
        $usersCount = User::where('department_id', $department->id)->count();
        
        // Get latest materials
        $latestMaterials = Material::where('department_id', $department->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'type', 'created_at']);
            
        // Get active users
        $activeUsers = User::where('department_id', $department->id)
            ->where('is_active', true)
            ->count();
        
        $departmentWithStats = [
            'id' => $department->id,
            'name' => $department->name,
            'description' => $department->description,
            'slug' => $department->slug,
            'is_active' => $department->is_active ?? true,
            'order' => $department->order ?? 0,
            'color' => $department->color,
            'created_at' => $department->created_at,
            'updated_at' => $department->updated_at,
            // Statistics
            'materials_count' => $materialsCount,
            'videos_count' => $videosCount,
            'courses_count' => $coursesCount,
            'users_count' => $usersCount,
            'active_users_count' => $activeUsers,
            // Additional data
            'latest_materials' => $latestMaterials,
            // For backward compatibility
            'total_materials' => $materialsCount,
            'total_videos' => $videosCount,
            'total_courses' => $coursesCount,
            'total_users' => $usersCount,
            // For frontend component
            'stats' => [
                'materials' => $materialsCount,
                'videos' => $videosCount,
                'courses' => $coursesCount,
                'users' => $usersCount,
                'active_users' => $activeUsers,
                'updated_at' => $department->updated_at
            ]
        ];
        
        return response()->json(['department' => $departmentWithStats]);
    }

    // Store a new department - UNCHANGED
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:departments,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? 'blue',
            'order' => $request->order ?? 0,
            'is_active' => $request->is_active ?? true,
            'slug' => \Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Department created', 'department' => $department]);
    }

    // Update a department - UNCHANGED
    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('departments')->ignore($department->id),
            ],
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $department->update([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? $department->color,
            'order' => $request->order ?? $department->order,
            'is_active' => $request->is_active ?? $department->is_active,
            'slug' => $request->name !== $department->name ? \Str::slug($request->name) : $department->slug,
        ]);

        return response()->json(['message' => 'Department updated', 'department' => $department]);
    }

    // Delete a department - UNCHANGED
    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['message' => 'Department deleted']);
    }
}