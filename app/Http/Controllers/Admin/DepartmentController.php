<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    // List all departments
    public function index()
    {
        try {
            $departments = Department::orderBy('id', 'asc')->get(['id', 'name', 'description']);
            
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
            ], 500);
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