<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    // List all departments - FIXED VERSION
    public function index()
    {
        try {
            // Simple query to get departments - DON'T query is_active if it doesn't exist
            $departments = Department::orderBy('order')
                ->get(['id', 'name', 'description', 'slug', 'color', 'order', 'created_at', 'updated_at']);
            
            // Return simple response without statistics first
            return response()->json([
                'success' => true,
                'message' => 'Departments fetched successfully',
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
            
        } catch (\Exception $e) {
            \Log::error('DepartmentController index error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Unable to fetch departments',
                'message' => $e->getMessage(),
                'departments' => [],
                'statistics' => [
                    'total_departments' => 0,
                    'total_materials' => 0,
                    'total_videos' => 0,
                    'total_users' => 0
                ]
            ], 500); // Return 500 for server errors
        }
    }

    // Show a single department
    public function show($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Department details fetched successfully',
                'department' => $department
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
                'color' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:0',
            ]);

            $department = Department::create([
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color ?? 'blue',
                'order' => $request->order ?? 0,
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
            ]);

            $department->update([
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color ?? $department->color,
                'order' => $request->order ?? $department->order,
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