<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

class LevelController extends Controller
{
    /**
     * PUBLIC: GET /api/levels
     * Used by frontend filters & dropdowns.
     */
    public function publicIndex()
    {
        $levels = Cache::remember('public_levels_v2', 600, function () {
            return Level::active()
                ->ordered()
                ->get(['id', 'name', 'slug', 'code', 'icon', 'color', 'type', 'order']);
        });

        return response()->json([
            'success' => true,
            'levels'  => $levels,
            'grouped' => [
                'specializations' => $levels->where('type', 'specialization')->values(),
                'academic'        => $levels->where('type', 'academic')->values(),
            ],
        ]);
    }

    /**
     * ADMIN: GET /api/admin/levels
     */
    public function index(Request $request)
    {
        $query = Level::withCount('materials');

        if ($request->filled('type') && in_array($request->type, ['specialization', 'academic'])) {
            $query->where('type', $request->type);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $levels = $query->ordered()->get();

        return response()->json([
            'success'    => true,
            'levels'     => $levels,
            'statistics' => [
                'total'           => $levels->count(),
                'specializations' => $levels->where('type', 'specialization')->count(),
                'academic'        => $levels->where('type', 'academic')->count(),
                'active'          => $levels->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * ADMIN: POST /api/admin/levels
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:levels,name',
            'code'        => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:20',
            'color'       => 'nullable|string|max:20',
            'type'        => 'required|in:specialization,academic',
            'order'       => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $base = $validated['slug'];
        $i = 1;
        while (Level::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $base . '-' . $i++;
        }

        $level = Level::create($validated);
        Cache::forget('public_levels_v2');

        return response()->json([
            'success' => true,
            'message' => 'Level created successfully',
            'level'   => $level,
        ], 201);
    }

    /**
     * ADMIN: PUT /api/admin/levels/{level}
     */
    public function update(Request $request, Level $level)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255',
                Rule::unique('levels', 'name')->ignore($level->id)],
            'code'        => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:20',
            'color'       => 'nullable|string|max:20',
            'type'        => 'sometimes|in:specialization,academic',
            'order'       => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $level->name) {
            $newSlug = Str::slug($validated['name']);
            $base = $newSlug;
            $i = 1;
            while (Level::where('slug', $newSlug)->where('id', '!=', $level->id)->exists()) {
                $newSlug = $base . '-' . $i++;
            }
            $validated['slug'] = $newSlug;
        }

        $level->update($validated);
        Cache::forget('public_levels_v2');

        return response()->json([
            'success' => true,
            'message' => 'Level updated successfully',
            'level'   => $level->fresh(),
        ]);
    }

    /**
     * ADMIN: DELETE /api/admin/levels/{level}
     */
    public function destroy(Level $level)
    {
        $materials = $level->materials()->count();
        $users     = $level->users()->count();

        if ($materials > 0 || $users > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete. It has {$materials} materials and {$users} users.",
            ], 422);
        }

        $level->delete();
        Cache::forget('public_levels_v2');

        return response()->json(['success' => true, 'message' => 'Level deleted']);
    }

    /**
     * ADMIN: POST /api/admin/levels/reorder
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'levels'         => 'required|array',
            'levels.*.id'    => 'required|exists:levels,id',
            'levels.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['levels'] as $row) {
            Level::where('id', $row['id'])->update(['order' => $row['order']]);
        }

        Cache::forget('public_levels_v2');

        return response()->json(['success' => true, 'message' => 'Reordered']);
    }
}