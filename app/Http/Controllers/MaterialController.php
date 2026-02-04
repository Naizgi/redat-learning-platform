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
                'department_id' => $request->department_id,
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $query = Material::where('is_published', true)
                ->with(['department']) // Only load department, not comments for listing
                ->withCount(['likes', 'comments'])
                ->orderByDesc('created_at');

            // Apply department filter
            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            // If user is authenticated and no department_id provided, use user's department
            elseif ($request->user() && $request->user()->department_id) {
                $query->where('department_id', $request->user()->department_id);
            }
            // If no department specified, return empty or all? Let's return empty
            else {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No department specified'
                ]);
            }

            // Apply other filters
            if ($request->filled('level')) {
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

            // Handle limit
            if ($request->filled('limit')) {
                $materials = $query->limit((int)$request->limit)->get();
            } else {
                // Default pagination
                $materials = $query->paginate($request->per_page ?? 20);
            }

            // Add file URLs to each material
            $materials->transform(function ($material) {
                $material->file_url = $this->getFileUrl($material);
                $material->download_url = $this->getDownloadUrl($material);
                return $material;
            });

            return response()->json([
                'success' => true,
                'data' => $materials,
                'count' => $materials->count()
            ]);

        } catch (\Throwable $e) {
            \Log::error('Materials index error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
                'error' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function show(Material $material)
    {
        $this->authorizeAccess($material);

        // Increment view count
        $material->increment('views_count');

        // Load related data - removed 'course' relationship
        $material->load([
            'comments.user', 
            'department',
            'likes' => function ($query) {
                $query->where('user_id', auth()->id());
            }
        ]);

        // Add file URLs to the response
        $material->file_url = $this->getFileUrl($material);
        $material->download_url = $this->getDownloadUrl($material);
        $material->file_path = $this->getStoragePath($material);

        return response()->json([
            'success' => true,
            'data' => $material
        ]);
    }

    public function stream(Material $material)
{
    try {
        // Remove all authentication checks for public streaming
        // Only check if material exists and is published
        if (!$material->exists) {
            abort(404, 'Material not found');
        }
        
        // Check if material is published
        if (!$material->is_published) {
            \Log::warning('Attempt to access unpublished material', [
                'material_id' => $material->id,
                'ip' => request()->ip()
            ]);
            abort(404, 'Material not available');
        }
        
        // Get the correct storage path
        $storagePath = $this->getStoragePath($material);
        
        // Debug logging
        \Log::info('Attempting to stream file', [
            'material_id' => $material->id,
            'file_name' => $material->file_name,
            'storage_path' => $storagePath,
            'storage_disk' => config('filesystems.default'),
            'all_disks' => config('filesystems.disks')
        ]);
        
        // Check if file exists using multiple methods
        $fileExists = Storage::exists($storagePath);
        
        if (!$fileExists) {
            // Try to find the file in common locations
            $possiblePaths = [
                'public/materials/' . $material->file_name,
                'materials/' . $material->file_name,
                'public/' . $material->file_name,
                $material->file_name,
                $material->file_path,
                'app/public/materials/' . $material->file_name,
                storage_path('app/public/materials/' . $material->file_name),
                storage_path('app/materials/' . $material->file_name)
            ];
            
            \Log::info('Checking alternative paths', ['paths' => $possiblePaths]);
            
            foreach ($possiblePaths as $path) {
                try {
                    if (Storage::exists($path)) {
                        $storagePath = $path;
                        \Log::info('Found file at alternative path', ['path' => $path]);
                        $fileExists = true;
                        break;
                    }
                } catch (\Exception $e) {
                    // Continue checking other paths
                    continue;
                }
            }
            
            // Also check physical file system
            if (!$fileExists) {
                $physicalPath = storage_path('app/' . $storagePath);
                if (file_exists($physicalPath)) {
                    \Log::info('Found file via physical path', ['path' => $physicalPath]);
                    $fileExists = true;
                } else {
                    // Try with public prefix
                    $physicalPath = storage_path('app/public/' . $material->file_name);
                    if (file_exists($physicalPath)) {
                        $storagePath = 'public/' . $material->file_name;
                        $fileExists = true;
                    }
                }
            }
            
            if (!$fileExists) {
                \Log::error('File not found in any location', [
                    'material_id' => $material->id,
                    'file_name' => $material->file_name,
                    'checked_paths' => $possiblePaths
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage system',
                    'error' => 'FILE_NOT_FOUND'
                ], 404);
            }
        }
        
        // Increment view count
        $material->increment('views_count');
        
        // Get file details
        try {
            $mimeType = Storage::mimeType($storagePath);
            $fileSize = Storage::size($storagePath);
            
            \Log::info('File details', [
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'final_path' => $storagePath
            ]);
        } catch (\Exception $e) {
            \Log::warning('Could not get file details', ['error' => $e->getMessage()]);
            $mimeType = 'application/octet-stream';
            $fileSize = 0;
        }
        
        // Determine appropriate headers
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($material->file_name) . '"',
            'Content-Length' => $fileSize,
            'Cache-Control' => 'public, max-age=31536000',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];
        
        // For PDF files, add additional headers for better browser support
        if ($mimeType === 'application/pdf') {
            $headers['Content-Type'] = 'application/pdf';
            $headers['Content-Disposition'] = 'inline; filename="' . basename($material->file_name) . '"';
        }
        
        \Log::info('Streaming file with headers', ['headers' => $headers]);
        
        // Return the file response
        return Storage::response($storagePath, basename($material->file_name), $headers);
        
    } catch (\Exception $e) {
        \Log::error('Streaming error', [
            'material_id' => $material->id ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error streaming file: ' . $e->getMessage(),
            'error' => 'STREAM_ERROR'
        ], 500);
    }
}

    public function download(Material $material)
    {
        $this->authorizeAccess($material);

        // Get the correct storage path
        $storagePath = $this->getStoragePath($material);
        
        if (!Storage::exists($storagePath)) {
            // Try to find the file in common locations
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

        // Increment download count
        $material->increment('download_count');

        \Log::info('Downloading file', [
            'material_id' => $material->id,
            'file_name' => $material->file_name,
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
            'progress' => $request->progress, // Changed from progress_percentage
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

    // Get user progress - handle missing progress table gracefully
    $userProgress = null;
    try {
        $userProgress = $material->progress()->where('user_id', auth()->id())->first();
    } catch (\Exception $e) {
        \Log::warning('Error fetching progress', ['error' => $e->getMessage()]);
        // Continue without progress data
    }

    $stats = [
        'total_views' => $material->views_count ?? 0,
        'total_downloads' => $material->download_count ?? 0,
        'total_likes' => $material->likes()->count() ?? 0,
        'total_comments' => $material->comments()->count() ?? 0,
        'average_rating' => $material->average_rating ?? 0,
        'user_liked' => $material->likes()->where('user_id', auth()->id())->exists(),
        'user_progress' => $userProgress ? [
            'progress_percentage' => $userProgress->progress ?? 0,
            'time_spent_seconds' => $userProgress->time_spent_seconds ?? 0,
            'last_page' => $userProgress->last_page ?? 1,
            'completed' => $userProgress->completed ?? false
        ] : [
            'progress_percentage' => 0,
            'time_spent_seconds' => 0,
            'last_page' => 1,
            'completed' => false
        ]
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
            ->orderBy('views_count', 'desc')
            ->orderBy('download_count', 'desc')
            ->limit(10);

        if ($request->has('exclude')) {
            $query->whereNotIn('id', explode(',', $request->exclude));
        }

        $materials = $query->get();
        
        // Add file URLs to each material
        $materials->transform(function ($material) {
            $material->file_url = $this->getFileUrl($material);
            $material->download_url = $this->getDownloadUrl($material);
            return $material;
        });

        return response()->json([
            'success' => true,
            'data' => $materials
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

        // Check if user has access to this material's department
        // Allow admin access or same department access
        if (!$user->isAdmin() && (int)$material->department_id !== (int)$user->department_id) {
            \Log::warning('Department access denied', [
                'user_id' => $user->id,
                'user_department' => $user->department_id,
                'material_department' => $material->department_id,
                'material_id' => $material->id
            ]);
            abort(403, 'You do not have access to this material. Your department: ' . $user->department_id . ', Material department: ' . $material->department_id);
        }
    }

    /**
     * Helper method to get the correct storage path
     */
    private function getStoragePath(Material $material): string
    {
        // If file_path is already set and exists, use it
        if (!empty($material->file_path) && Storage::exists($material->file_path)) {
            return $material->file_path;
        }

        // Try common storage paths
        $possiblePaths = [
            'public/materials/' . $material->file_name,
            'materials/' . $material->file_name,
            'public/' . $material->file_name,
            $material->file_name
        ];

        foreach ($possiblePaths as $path) {
            if (Storage::exists($path)) {
                return $path;
            }
        }

        // Default to public/materials directory
        return 'public/materials/' . $material->file_name;
    }

    /**
     * Helper method to get the file URL for streaming/viewing
     */
    private function getFileUrl(Material $material): string
    {
        return route('materials.stream', ['material' => $material->id]);
    }

    /**
     * Helper method to get the download URL
     */
    private function getDownloadUrl(Material $material): string
    {
        return route('materials.download', ['material' => $material->id]);
    }

    /**
     * Helper method to generate public URL for files in storage
     */
    private function getPublicUrl(Material $material): ?string
    {
        $storagePath = $this->getStoragePath($material);
        
        if (Storage::exists($storagePath)) {
            return Storage::url($storagePath);
        }

        return null;
    }

    // For admin dashboard (separate endpoint)
    public function getAllMaterials(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $query = Material::with(['department']) // Removed 'course'
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
        
        $materials = $query->paginate($perPage);
        
        // Add file URLs to each material
        $materials->transform(function ($material) {
            $material->file_url = $this->getFileUrl($material);
            $material->download_url = $this->getDownloadUrl($material);
            return $material;
        });

        return response()->json([
            'success' => true,
            'data' => $materials
        ]);
    }
}