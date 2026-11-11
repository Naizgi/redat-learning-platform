<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class SubDepartmentController extends Controller
{
    /** PUBLIC: GET /api/sub-departments?department_id=X */
    public function publicIndex(Request $request)
    {
        $q = SubDepartment::active()->ordered()->with('department');

        if ($request->filled('department_id')) {
            $q->where('department_id', $request->department_id);
        }

        return response()->json([
            'success'         => true,
            'sub_departments' => $q->get(),
        ]);
    }

    /** ADMIN: GET /api/admin/sub-departments */
    public function index(Request $request)
    {
        $q = SubDepartment::with('department')->withCount('materials');

        if ($request->filled('department_id')) {
            $q->where('department_id', $request->department_id);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'success'         => true,
            'sub_departments' => $q->ordered()->get(),
        ]);
    }

    /** ADMIN: POST /api/admin/sub-departments */
    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name'          => [
                'required', 'string', 'max:255',
                Rule::unique('sub_departments')->where(
                    fn($q) => $q->where('department_id', $request->department_id)
                ),
            ],
            'icon'        => 'nullable|string|max:20',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'order'       => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $base = $data['slug']; $i = 1;
        while (SubDepartment::where('department_id', $data['department_id'])
                ->where('slug', $data['slug'])->exists()) {
            $data['slug'] = $base . '-' . $i++;
        }

        $sub = SubDepartment::create($data);
        Cache::forget('public_sub_departments');

        return response()->json([
            'success'        => true,
            'message'        => 'Sub-division created',
            'sub_department' => $sub->load('department'),
        ], 201);
    }

    /** ADMIN: PUT /api/admin/sub-departments/{subDepartment} */
    public function update(Request $request, SubDepartment $subDepartment)
    {
        $deptId = $request->input('department_id', $subDepartment->department_id);

        $data = $request->validate([
            'department_id' => 'sometimes|exists:departments,id',
            'name'          => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('sub_departments')
                    ->where(fn($q) => $q->where('department_id', $deptId))
                    ->ignore($subDepartment->id),
            ],
            'icon'        => 'nullable|string|max:20',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'order'       => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $subDepartment->update($data);
        Cache::forget('public_sub_departments');

        return response()->json([
            'success'        => true,
            'message'        => 'Sub-division updated',
            'sub_department' => $subDepartment->fresh()->load('department'),
        ]);
    }

    /** ADMIN: DELETE /api/admin/sub-departments/{subDepartment} */
    public function destroy(SubDepartment $subDepartment)
    {
        $count = $subDepartment->materials()->count();
        if ($count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete — {$count} materials are assigned. Reassign them first.",
            ], 422);
        }

        $subDepartment->delete();
        Cache::forget('public_sub_departments');

        return response()->json([
            'success' => true,
            'message' => 'Sub-division deleted',
        ]);
    }
}