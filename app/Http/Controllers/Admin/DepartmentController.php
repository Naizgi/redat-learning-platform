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
        $departments = Department::all();
        return response()->json(['departments' => $departments]);
    }

    // Store a new department
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:departments,name',
            'description' => 'nullable|string',
        ]);

        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Department created', 'department' => $department]);
    }

    // Show a single department
    public function show(Department $department)
    {
        return response()->json(['department' => $department]);
    }

    // Update a department
    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('departments')->ignore($department->id),
            ],
            'description' => 'nullable|string',
        ]);

        $department->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Department updated', 'department' => $department]);
    }

    // Delete a department
    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['message' => 'Department deleted']);
    }
}
