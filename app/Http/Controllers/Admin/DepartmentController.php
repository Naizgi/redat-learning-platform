<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Validation\Rule;
use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    // List all departments with statistics
    public function index()
    {
        try {
            // Get all active departments
            $departments = Department::where('is_active', true)
                ->orderBy('order')
                ->get();
            
            // Get all statistics in efficient queries
            $materialsStats = Material::select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id')
                ->pluck('total', 'department_id')
                ->toArray();
            
            $usersStats = User::select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id')
                ->pluck('total', 'department_id')
                ->toArray();
            
            // Get video counts for all departments in one query
            $videoStats = Material::where('type', 'video')
                ->orWhere(function($query) {
                    $query->where('file_name', 'like', '%.mp4')
                        ->orWhere('file_name', 'like', '%.avi')
                        ->orWhere('file_name', 'like', '%.mov')
                        ->orWhere('file_name', 'like', '%.wmv')
                        ->orWhere('file_name', 'like', '%.flv')
                        ->orWhere('file_name', 'like', '%.mkv')
                        ->orWhere('file_name', 'like', '%.webm')
                        ->orWhere('file_name', 'like', '%.m4v');
                })
                ->select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id')
                ->pluck('total', 'department_id')
                ->toArray();
            
            // Transform departments to include statistics
            $departmentsWithStats = $departments->map(function ($department) use ($materialsStats, $usersStats, $videoStats) {
                // Get statistics for this department
                $materialsCount = $materialsStats[$department->id] ?? 0;
                $usersCount = $usersStats[$department->id] ?? 0;
                $videosCount = $videoStats[$department->id] ?? 0;
                
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'slug' => $department->slug,
                    'is_active' => $department->is_active,
                    'order' => $department->order ?? 0,
                    'color' => $department->color ?? 'blue',
                    'created_at' => $department->created_at,
                    'updated_at' => $department->updated_at,
                    // Statistics
                    'materials_count' => $materialsCount,
                    'videos_count' => $videosCount,
                    'users_count' => $usersCount,
                    // For backward compatibility
                    'total_materials' => $materialsCount,
                    'total_videos' => $videosCount,
                    'total_users' => $usersCount,
                    // For frontend
                    'stats' => [
                        'materials' => $materialsCount,
                        'videos' => $videosCount,
                        'users' => $usersCount,
                        'updated_at' => $department->updated_at
                    ]
                ];
            });
            
            // Calculate totals
            $totalDepartments = $departments->count();
            $totalMaterials = array_sum($materialsStats);
            $totalVideos = array_sum($videoStats);
            $totalUsers = array_sum($usersStats);
            
            return response()->json([
                'success' => true,
                'message' => 'Departments fetched successfully',
                'departments' => $departmentsWithStats,
                'statistics' => [
                    'total_departments' => $totalDepartments,
                    'total_materials' => $totalMaterials,
                    'total_videos' => $totalVideos,
                    'total_users' => $totalUsers
                ],
                'meta' => [
                    'count' => $totalDepartments,
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController index error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback: return basic departments without statistics
            try {
                $departments = Department::where('is_active', true)
                    ->orderBy('order')
                    ->get(['id', 'name', 'description', 'slug', 'color', 'order', 'is_active', 'created_at', 'updated_at']);
                
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
                    'warning' => 'Statistics temporarily unavailable',
                    'meta' => [
                        'count' => $departments->count(),
                        'timestamp' => now()->toISOString()
                    ]
                ]);
            } catch (\Exception $fallbackError) {
                \Log::error('DepartmentController fallback error: ' . $fallbackError->getMessage());
                
                return response()->json([
                    'success' => false,
                    'error' => 'Unable to fetch departments',
                    'message' => 'Please try again later',
                    'departments' => [],
                    'statistics' => [
                        'total_departments' => 0,
                        'total_materials' => 0,
                        'total_videos' => 0,
                        'total_users' => 0
                    ],
                    'meta' => [
                        'count' => 0,
                        'timestamp' => now()->toISOString()
                    ]
                ], 200); // Return 200 with empty data instead of 500
            }
        }
    }

    // Show a single department with detailed statistics
    public function show($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            // Get statistics
            $materialsCount = Material::where('department_id', $department->id)->count();
            $usersCount = User::where('department_id', $department->id)->count();
            
            // Get video count using type column or file extensions
            $videosCount = Material::where('department_id', $department->id)
                ->where(function($query) {
                    $query->where('type', 'video')
                        ->orWhere('file_name', 'like', '%.mp4')
                        ->orWhere('file_name', 'like', '%.avi')
                        ->orWhere('file_name', 'like', '%.mov')
                        ->orWhere('file_name', 'like', '%.wmv')
                        ->orWhere('file_name', 'like', '%.flv')
                        ->orWhere('file_name', 'like', '%.mkv');
                })->count();
            
            // Get latest materials
            $latestMaterials = Material::where('department_id', $department->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'type', 'file_name', 'created_at']);
            
            // Get active users
            $activeUsers = User::where('department_id', $department->id)
                ->where('is_active', true)
                ->count();
            
            return response()->json([
                'success' => true,
                'message' => 'Department details fetched successfully',
                'department' => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'slug' => $department->slug,
                    'is_active' => $department->is_active,
                    'order' => $department->order ?? 0,
                    'color' => $department->color ?? 'blue',
                    'created_at' => $department->created_at,
                    'updated_at' => $department->updated_at,
                    // Statistics
                    'materials_count' => $materialsCount,
                    'videos_count' => $videosCount,
                    'users_count' => $usersCount,
                    'active_users_count' => $activeUsers,
                    // Additional data
                    'latest_materials' => $latestMaterials,
                    // For backward compatibility
                    'total_materials' => $materialsCount,
                    'total_videos' => $videosCount,
                    'total_users' => $usersCount,
                    // For frontend
                    'stats' => [
                        'materials' => $materialsCount,
                        'videos' => $videosCount,
                        'users' => $usersCount,
                        'active_users' => $activeUsers,
                        'updated_at' => $department->updated_at
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Department not found',
                'message' => 'The requested department does not exist'
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
                'color' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:0',
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
                'color' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:0',
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