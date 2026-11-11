<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        try {
            \Log::info('Materials index called', [
                'department_id'     => $request->department_id,
                'sub_department_id' => $request->sub_department_id,
                'level_id'          => $request->level_id,
                'user_id'           => $request->user() ? $request->user()->id : null
            ]);

            $query = Material::where('is_published', true)
                ->with(['department', 'subDepartment'])
                ->withCount(['likes', 'comments'])
                ->orderByDesc('created_at');

            // Apply department filter
            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            } elseif ($request->user() && $request->user()->department_id) {
                $query->where('department_id', $request->user()->department_id);
            } else {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'message' => 'No department specified'
                ]);
            }

            // ── NEW: Filter by sub-department ──
            if ($request->filled('sub_department_id')) {
                $query->where('sub_department_id', $request->sub_department_id);
            }

            // ── Apply level_id filter ──
            if ($request->filled('level_id')) {
                $query->where('level_id', $request->level_id);
            } elseif ($request->filled('level')) {
                $query->where('level', $request->level);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%$search%")
                      ->orWhere('description', 'like', "%$search%")
                      ->orWhere('tags', 'like', "%$search%");
                });
            }

            if ($request->filled('limit')) {
                $materials = $query->limit((int)$request->limit)->get();
            } else {
                $materials = $query->paginate($request->per_page ?? 20);
            }

            $materials->transform(function ($material) {
                $material->file_url     = $this->getFileUrl($material);
                $material->download_url = $this->getDownloadUrl($material);
                return $material;
            });

            return response()->json([
                'success' => true,
                'data'    => $materials,
                'count'   => $materials->count()
            ]);

        } catch (\Throwable $e) {
            \Log::error('Materials index error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
                'error'   => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function getFeatured(Request $request)
    {
        try {
            \Log::info('Featured materials requested', [
                'ip'            => $request->ip(),
                'user_agent'    => $request->userAgent(),
                'public_access' => true
            ]);

            $departments = \App\Models\Department::all();

            $featuredMaterials = collect();

            if ($departments->isEmpty()) {
                $featuredMaterials = Material::where('is_published', true)
                    ->with(['department', 'subDepartment'])
                    ->withCount(['likes', 'comments'])
                    ->orderByDesc('views_count')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get();
            } else {
                foreach ($departments as $department) {
                    $materials = Material::where('is_published', true)
                        ->where('department_id', $department->id)
                        ->with(['department', 'subDepartment'])
                        ->withCount(['likes', 'comments'])
                        ->orderByDesc('views_count')
                        ->orderByDesc('created_at')
                        ->limit(2)
                        ->get();

                    $materials->transform(function ($material) {
                        $material->file_url     = $this->getFileUrl($material);
                        $material->download_url = $this->getDownloadUrl($material);
                        return $material;
                    });

                    $featuredMaterials = $featuredMaterials->merge($materials);
                }

                if ($featuredMaterials->isEmpty()) {
                    $featuredMaterials = Material::where('is_published', true)
                        ->with(['department', 'subDepartment'])
                        ->withCount(['likes', 'comments'])
                        ->orderByDesc('views_count')
                        ->orderByDesc('created_at')
                        ->limit(8)
                        ->get();
                }
            }

            $featuredMaterials->transform(function ($material) {
                $material->file_url     = $this->getFileUrl($material);
                $material->download_url = $this->getDownloadUrl($material);
                return $material;
            });

            $featuredMaterials = $featuredMaterials->take(8);

            return response()->json([
                'success' => true,
                'data'    => $featuredMaterials,
                'count'   => $featuredMaterials->count(),
                'message' => 'Featured materials retrieved successfully'
            ]);

        } catch (\Throwable $e) {
            \Log::error('Featured materials error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving featured materials',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function show(Material $material)
    {
        $this->authorizeAccess($material);

        $material->increment('views_count');

        $material->load([
            'comments.user',
            'department',
            'subDepartment',
            'level',
            'likes' => function ($query) {
                $query->where('user_id', auth()->id());
            }
        ]);

        $material->file_url     = $this->getFileUrl($material);
        $material->download_url = $this->getDownloadUrl($material);
        $material->file_path    = $this->getStoragePath($material);

        return response()->json([
            'success' => true,
            'data'    => $material
        ]);
    }

    public function stream(Material $material)
    {
        try {
            \Log::info('========== STREAM DEBUG START ==========');
            \Log::info('Material ID: ' . $material->id);
            \Log::info('Material Title: ' . $material->title);
            \Log::info('File name from DB: ' . $material->file_name);
            \Log::info('File path from DB: ' . $material->file_path);
            \Log::info('Is published: ' . ($material->is_published ? 'Yes' : 'No'));

            if (!$material->exists) {
                \Log::error('Material does not exist');
                abort(404, 'Material not found');
            }

            if (!$material->is_published) {
                \Log::warning('Material not published');
                abort(404, 'Material not available');
            }

            $storagePath = $this->getStoragePath($material);
            \Log::info('Storage path from helper: ' . $storagePath);

            $fullPath = storage_path('app/' . $storagePath);
            \Log::info('Full system path: ' . $fullPath);
            \Log::info('File exists check: ' . (file_exists($fullPath) ? 'YES' : 'NO'));
            \Log::info('File readable: ' . (is_readable($fullPath) ? 'YES' : 'NO'));
            \Log::info('File size: ' . (file_exists($fullPath) ? filesize($fullPath) : 'N/A'));

            $storageExists = Storage::exists($storagePath);
            \Log::info('Storage::exists result: ' . ($storageExists ? 'YES' : 'NO'));

            if (!$storageExists) {
                \Log::error('File not found in storage');

                \Log::info('Searching for file by filename...');
                $fileName = $material->file_name;
                $files = Storage::files('public/materials');
                $found = false;
                foreach ($files as $file) {
                    if (str_contains($file, $fileName) || str_contains($fileName, basename($file))) {
                        \Log::info('Found matching file: ' . $file);
                        $storagePath = $file;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File not found in storage system',
                        'debug'   => [
                            'file_name'    => $material->file_name,
                            'file_path'    => $material->file_path,
                            'storage_path' => $storagePath,
                            'full_path'    => $fullPath,
                            'public_url'   => asset('storage/materials/' . urlencode($material->file_name))
                        ]
                    ], 404);
                }
            }

            $material->increment('views_count');

            try {
                $mimeType = Storage::mimeType($storagePath);
                $fileSize = Storage::size($storagePath);
                \Log::info('MIME type: ' . $mimeType);
                \Log::info('File size: ' . $fileSize);
            } catch (\Exception $e) {
                \Log::error('Error getting file details: ' . $e->getMessage());
                $mimeType = 'video/mp4';
                $fileSize = Storage::size($storagePath);
            }

            $headers = [
                'Content-Type'                 => $mimeType,
                'Content-Length'               => $fileSize,
                'Accept-Ranges'                => 'bytes',
                'Cache-Control'                => 'public, max-age=31536000',
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Range',
                'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
            ];

            \Log::info('Headers prepared: ' . json_encode($headers));
            \Log::info('========== STREAM DEBUG END ==========');

            return Storage::response($storagePath, null, $headers);

        } catch (\Exception $e) {
            \Log::error('STREAM ERROR: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile());
            \Log::error('Line: ' . $e->getLine());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error streaming file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getComments(Material $material)
    {
        try {
            if (!$material->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Material not found'
                ], 404);
            }

            $comments = $material->comments()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data'    => $comments,
                'message' => 'Comments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching comments: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments'
            ], 500);
        }
    }

    public function download(Material $material)
    {
        $this->authorizeAccess($material);

        $storagePath = $this->getStoragePath($material);

        if (!Storage::exists($storagePath)) {
            $possiblePaths = [
                'public/materials/' . $material->file_name,
                'materials/' . $material->file_name,
                $material->file_name,
                $material->file_path
            ];

            foreach ($possiblePaths as $path) {
                if (Storage::exists($path)) {
                    $storagePath = $path;
                    break;
                }
            }

            if (!Storage::exists($storagePath)) {
                abort(404, 'File not found');
            }
        }

        $material->increment('download_count');

        \Log::info('Downloading file', [
            'material_id'  => $material->id,
            'file_name'    => $material->file_name,
            'storage_path' => $storagePath
        ]);

        return Storage::download($storagePath, $material->file_name);
    }

    public function like(Material $material)
    {
        $this->authorizeAccess($material);

        $user = auth()->user();
        $existingLike = $material->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
            $message = 'Material unliked successfully';
            $liked = false;
        } else {
            DB::table('material_likes')->insert([
                'material_id' => $material->id,
                'user_id'     => $user->id
            ]);
            $message = 'Material liked successfully';
            $liked = true;
        }

        $material->loadCount('likes');

        return response()->json([
            'success'     => true,
            'message'     => $message,
            'likes_count' => $material->likes_count,
            'liked'       => $liked
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
            'data'    => $comment->load('user')
        ]);
    }

    public function updateProgress(Request $request, Material $material)
    {
        $this->authorizeAccess($material);

        $request->validate([
            'progress'   => 'required|numeric|min:0|max:100',
            'time_spent' => 'required|integer|min:0'
        ]);

        $progress = $material->progress()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'progress'           => $request->progress,
                'time_spent_seconds' => $request->time_spent,
                'completed'          => $request->progress >= 100
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress updated successfully',
            'data'    => $progress
        ]);
    }

    public function getStats(Material $material)
    {
        $this->authorizeAccess($material);

        $userProgress = null;
        try {
            $userProgress = $material->progress()->where('user_id', auth()->id())->first();
        } catch (\Exception $e) {
            \Log::warning('Error fetching progress', ['error' => $e->getMessage()]);
        }

        $stats = [
            'total_views'     => $material->views_count ?? 0,
            'total_downloads' => $material->download_count ?? 0,
            'total_likes'     => $material->likes()->count() ?? 0,
            'total_comments'  => $material->comments()->count() ?? 0,
            'average_rating'  => $material->average_rating ?? 0,
            'user_liked'      => $material->likes()->where('user_id', auth()->id())->exists(),
            'user_progress'   => $userProgress ? [
                'progress_percentage' => $userProgress->progress ?? 0,
                'time_spent_seconds'  => $userProgress->time_spent_seconds ?? 0,
                'last_page'           => $userProgress->last_page ?? 1,
                'completed'           => $userProgress->completed ?? false
            ] : [
                'progress_percentage' => 0,
                'time_spent_seconds'  => 0,
                'last_page'           => 1,
                'completed'           => false
            ]
        ];

        return response()->json([
            'success' => true,
            'data'    => $stats
        ]);
    }

    public function getRecommended(Request $request)
    {
        $user = $request->user();

        $query = Material::where('is_published', true)
            ->where('department_id', $user->department_id)
            ->withCount(['likes', 'comments'])
            ->with(['department', 'subDepartment'])
            ->orderBy('views_count', 'desc')
            ->orderBy('download_count', 'desc')
            ->limit(10);

        if ($request->has('exclude')) {
            $query->whereNotIn('id', explode(',', $request->exclude));
        }

        $materials = $query->get();

        $materials->transform(function ($material) {
            $material->file_url     = $this->getFileUrl($material);
            $material->download_url = $this->getDownloadUrl($material);
            return $material;
        });

        return response()->json([
            'success' => true,
            'data'    => $materials
        ]);
    }

    private function authorizeAccess(Material $material)
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if (!$material->is_published) {
            abort(404, 'Material not found');
        }

        if (!$user->isAdmin() && (int)$material->department_id !== (int)$user->department_id) {
            \Log::warning('Department access denied', [
                'user_id'             => $user->id,
                'user_department'     => $user->department_id,
                'material_department' => $material->department_id,
                'material_id'         => $material->id
            ]);
            abort(403, 'You do not have access to this material. Your department: ' . $user->department_id . ', Material department: ' . $material->department_id);
        }
    }

    private function getStoragePath(Material $material): string
    {
        \Log::info('getStoragePath called for material ID: ' . $material->id);

        if (!empty($material->file_path)) {
            $cleanPath = ltrim($material->file_path, '/\\');

            $possiblePaths = [
                $cleanPath,
                'public/' . $cleanPath,
                'storage/app/public/' . $cleanPath,
                'materials/' . basename($cleanPath),
                'public/materials/' . basename($cleanPath),
            ];

            foreach ($possiblePaths as $path) {
                if (Storage::exists($path)) {
                    \Log::info('Found file at path (from file_path)', ['path' => $path, 'material_id' => $material->id]);
                    return $path;
                }
            }
        }

        if (!empty($material->file_name)) {
            $possiblePaths = [
                'public/materials/' . $material->file_name,
                'materials/' . $material->file_name,
                'public/' . $material->file_name,
                $material->file_name,
                'storage/app/public/materials/' . $material->file_name,
            ];

            foreach ($possiblePaths as $path) {
                if (Storage::exists($path)) {
                    \Log::info('Found file by filename', ['path' => $path, 'material_id' => $material->id]);

                    $publicUrl = asset('storage/materials/' . urlencode($material->file_name));
                    \Log::info('Public URL would be', ['url' => $publicUrl, 'material_id' => $material->id]);

                    return $path;
                }
            }
        }

        \Log::error('Could not find file for material', [
            'material_id' => $material->id,
            'file_path'   => $material->file_path,
            'file_name'   => $material->file_name
        ]);

        if (!empty($material->file_path)) {
            return 'public/materials/' . basename($material->file_path);
        }

        if (!empty($material->file_name)) {
            return 'public/materials/' . $material->file_name;
        }

        return 'public/materials/unknown';
    }

    private function getFileUrl(Material $material): string
    {
        return route('materials.stream', ['material' => $material->id]);
    }

    private function getDownloadUrl(Material $material): string
    {
        return route('materials.download', ['material' => $material->id]);
    }

    private function getPublicUrl(Material $material): ?string
    {
        $storagePath = $this->getStoragePath($material);

        if (Storage::exists($storagePath)) {
            return Storage::url($storagePath);
        }

        return null;
    }

    public function getAllMaterials(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $query = Material::with(['department', 'subDepartment'])
            ->withCount(['likes', 'comments']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('sub_department_id')) {
            $query->where('sub_department_id', $request->sub_department_id);
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

        $sortBy    = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);

        $materials = $query->paginate($perPage);

        $materials->transform(function ($material) {
            $material->file_url     = $this->getFileUrl($material);
            $material->download_url = $this->getDownloadUrl($material);
            return $material;
        });

        return response()->json([
            'success' => true,
            'data'    => $materials
        ]);
    }

    private function isVideoBrowserCompatible($mimeType, $fileName)
    {
        $compatibleMimeTypes = [
            'video/mp4',
            'video/webm',
            'video/ogg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska',
        ];

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $compatibleExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];

        return in_array($mimeType, $compatibleMimeTypes) || in_array($extension, $compatibleExtensions);
    }

    private function getVideoContentType($fileName)
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $contentTypes = [
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'ogg'  => 'video/ogg',
            'ogv'  => 'video/ogg',
            'mov'  => 'video/quicktime',
            'avi'  => 'video/x-msvideo',
            'mkv'  => 'video/x-matroska',
            'flv'  => 'video/x-flv',
            'wmv'  => 'video/x-ms-wmv',
        ];

        return $contentTypes[$extension] ?? 'video/mp4';
    }
}