<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::where('is_published', true)
            ->withCount('likes')
            ->with(['comments.user', 'department'])
            ->latest();

        // Apply department filter if user is logged in
        if ($request->user()) {
            $query->where('department_id', $request->user()->department_id);
        }

        // Allow filtering by multiple parameters
        if ($request->has('department')) {
            $query->whereHas('department', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->department . '%');
            });
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('tags', 'like', '%' . $search . '%');
            });
        }

        // Pagination or limit
        if ($request->has('limit')) {
            return $query->take($request->limit)->get();
        }

        if ($request->has('page') || $request->has('per_page')) {
            return $query->paginate($request->per_page ?? 20);
        }

        return $query->get();
    }

    public function show(Material $material)
    {
        $this->authorizeAccess($material);

        // Increment view count
        $material->increment('views');

        return response()->json([
            'success' => true,
            'data' => $material->load([
                'comments.user', 
                'department',
                'course',
                'likes' => function ($query) {
                    $query->where('user_id', auth()->id());
                }
            ])
        ]);
    }

    public function stream(Material $material)
    {
        $this->authorizeAccess($material);

        if (!Storage::exists($material->file_path)) {
            abort(404, 'File not found');
        }

        // Increment download count
        $material->increment('download_count');

        $headers = [
            'Content-Type' => Storage::mimeType($material->file_path),
            'Content-Disposition' => 'inline; filename="' . $material->file_name . '"',
        ];

        return Storage::response($material->file_path, $material->file_name, $headers);
    }

    public function download(Material $material)
    {
        $this->authorizeAccess($material);

        if (!Storage::exists($material->file_path)) {
            abort(404, 'File not found');
        }

        // Increment download count
        $material->increment('download_count');

        return Storage::download($material->file_path, $material->file_name);
    }

    public function like(Material $material)
    {
        $this->authorizeAccess($material);

        $user = auth()->user();
        $existingLike = $material->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
            $message = 'Material unliked successfully';
        } else {
            $material->likes()->create(['user_id' => $user->id]);
            $message = 'Material liked successfully';
        }

        $material->loadCount('likes');

        return response()->json([
            'success' => true,
            'message' => $message,
            'likes_count' => $material->likes_count,
            'liked' => !$existingLike
        ]);
    }

    public function comment(Request $request, Material $material)
    {
        $this->authorizeAccess($material);

        $request->validate([
            'comment' => 'required|string|max:1000'
        ]);

        $comment = $material->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => $comment->load('user')
        ]);
    }

    public function updateProgress(Request $request, Material $material)
    {
        $this->authorizeAccess($material);

        $request->validate([
            'progress' => 'required|numeric|min:0|max:100',
            'time_spent' => 'required|integer|min:0'
        ]);

        $progress = $material->progress()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'progress_percentage' => $request->progress,
                'time_spent_seconds' => $request->time_spent,
                'completed' => $request->progress >= 100
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress updated successfully',
            'data' => $progress
        ]);
    }

    public function getStats(Material $material)
    {
        $this->authorizeAccess($material);

        $stats = [
            'total_views' => $material->views,
            'total_downloads' => $material->download_count,
            'total_likes' => $material->likes_count,
            'total_comments' => $material->comments_count,
            'average_rating' => $material->average_rating,
            'user_liked' => $material->likes()->where('user_id', auth()->id())->exists(),
            'user_progress' => $material->progress()->where('user_id', auth()->id())->first(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function getRecommended(Request $request)
    {
        $user = $request->user();
        
        $query = Material::where('is_published', true)
            ->where('department_id', $user->department_id)
            ->withCount(['likes', 'comments'])
            ->with('department')
            ->orderBy('views', 'desc')
            ->orderBy('download_count', 'desc')
            ->limit(10);

        if ($request->has('exclude')) {
            $query->whereNotIn('id', explode(',', $request->exclude));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    private function authorizeAccess(Material $material)
    {
        if (!$material->is_published) {
            abort(404, 'Material not found');
        }

        if ($material->department_id !== auth()->user()->department_id) {
            abort(403, 'You do not have access to this material');
        }
    }

    // For admin dashboard (separate endpoint)
    public function getAllMaterials(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $query = Material::with(['department', 'course'])
            ->withCount(['likes', 'comments']);

        // Filtering
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        return $query->paginate($perPage);
    }
}