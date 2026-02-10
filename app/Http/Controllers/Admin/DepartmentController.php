<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Material;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    // List all departments with real statistics
    public function index()
    {
        try {
            // Get all departments
            $departments = Department::orderBy('id', 'asc')->get(['id', 'name', 'description']);
            
            // Initialize arrays for statistics
            $departmentsWithStats = [];
            $totalMaterials = 0;
            $totalVideos = 0;
            $totalUsers = 0;
            
            foreach ($departments as $department) {
                // Get materials count for this department
                $materialsCount = Material::where('department_id', $department->id)->count();
                
                // Get videos count for this department - UPDATED TO INCLUDE YOUTUBE TYPE
                $videosCount = Material::where('department_id', $department->id)
                    ->where(function($query) {
                        $query->where('type', 'video')
                            ->orWhere('type', 'youtube')  // ADD THIS LINE
                            ->orWhere('file_name', 'like', '%.mp4')
                            ->orWhere('file_name', 'like', '%.avi')
                            ->orWhere('file_name', 'like', '%.mov')
                            ->orWhere('file_name', 'like', '%.wmv')
                            ->orWhere('file_name', 'like', '%.flv')
                            ->orWhere('file_name', 'like', '%.mkv')
                            ->orWhere('file_name', 'like', '%.webm');
                    })->count();
                
                // Get users count for this department
                $usersCount = User::where('department_id', $department->id)->count();
                
                // Add to totals
                $totalMaterials += $materialsCount;
                $totalVideos += $videosCount;
                $totalUsers += $usersCount;
                
                // Add department with statistics
                $departmentsWithStats[] = [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'materials_count' => $materialsCount,
                    'videos_count' => $videosCount,
                    'users_count' => $usersCount,
                    // For backward compatibility with frontend
                    'total_materials' => $materialsCount,
                    'total_videos' => $videosCount,
                    'total_users' => $usersCount,
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Departments fetched successfully',
                'departments' => $departmentsWithStats,
                'statistics' => [
                    'total_departments' => $departments->count(),
                    'total_materials' => $totalMaterials,
                    'total_videos' => $totalVideos,
                    'total_users' => $totalUsers
                ],
                'meta' => [
                    'count' => $departments->count(),
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController index error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback: return departments without statistics
            try {
                $departments = Department::orderBy('id', 'asc')->get(['id', 'name', 'description']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Departments fetched (statistics unavailable)',
                    'departments' => $departments,
                    'statistics' => [
                        'total_departments' => $departments->count(),
                        'total_materials' => 0,
                        'total_videos' => 0,
                        'total_users' => 0
                    ],
                    'meta' => [
                        'count' => $departments->count(),
                        'timestamp' => now()->toISOString()
                    ]
                ]);
            } catch (\Exception $fallbackError) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unable to fetch departments',
                    'message' => $fallbackError->getMessage(),
                    'departments' => [],
                    'statistics' => [
                        'total_departments' => 0,
                        'total_materials' => 0,
                        'total_videos' => 0,
                        'total_users' => 0
                    ]
                ], 500);
            }
        }
    }

    // Show a single department with detailed statistics
    public function show($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            // Get real statistics
            $materialsCount = Material::where('department_id', $department->id)->count();
            $usersCount = User::where('department_id', $department->id)->count();
            
            // Get videos count - UPDATED TO INCLUDE YOUTUBE TYPE
            $videosCount = Material::where('department_id', $department->id)
                ->where(function($query) {
                    $query->where('type', 'video')
                        ->orWhere('type', 'youtube')  // ADD THIS LINE
                        ->orWhere('file_name', 'like', '%.mp4')
                        ->orWhere('file_name', 'like', '%.avi')
                        ->orWhere('file_name', 'like', '%.mov')
                        ->orWhere('file_name', 'like', '%.wmv')
                        ->orWhere('file_name', 'like', '%.flv')
                        ->orWhere('file_name', 'like', '%.mkv')
                        ->orWhere('file_name', 'like', '%.webm');
                })->count();
            
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
                'materials_count' => $materialsCount,
                'videos_count' => $videosCount,
                'users_count' => $usersCount,
                'active_users_count' => $activeUsers,
                'latest_materials' => $latestMaterials,
                // For backward compatibility
                'total_materials' => $materialsCount,
                'total_videos' => $videosCount,
                'total_users' => $usersCount,
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Department details fetched successfully',
                'department' => $departmentWithStats
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Department not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    // Store a new department
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:departments,name',
                'description' => 'nullable|string',
            ]);

            $department = Department::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'department' => $department
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to create department',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // Update a department
    public function update(Request $request, Department $department)
    {
        try {
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('departments')->ignore($department->id),
                ],
                'description' => 'nullable|string',
            ]);

            $department->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'department' => $department
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to update department',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // Delete a department
    public function destroy(Department $department)
    {
        try {
            // Check if department has materials or users
            $materialsCount = Material::where('department_id', $department->id)->count();
            $usersCount = User::where('department_id', $department->id)->count();
            
            if ($materialsCount > 0 || $usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete department',
                    'message' => 'Department has ' . $materialsCount . ' materials and ' . $usersCount . ' users. Remove them first.'
                ], 422);
            }
            
            $department->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController destroy error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete department',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}