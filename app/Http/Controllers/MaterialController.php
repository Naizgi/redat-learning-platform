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
            'exists' => Storage::exists($storagePath)
        ]);
        
        // Check if file exists
        if (!Storage::exists($storagePath)) {
            \Log::error('File not found in storage', [
                'material_id' => $material->id,
                'storage_path' => $storagePath,
                'file_name' => $material->file_name
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'File not found in storage system',
                'error' => 'FILE_NOT_FOUND'
            ], 404);
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
                'final_path' => $storagePath,
                'is_video' => str_starts_with($mimeType, 'video/')
            ]);
        } catch (\Exception $e) {
            \Log::warning('Could not get file details', ['error' => $e->getMessage()]);
            $mimeType = 'video/mp4'; // Default to mp4 for video files
            $fileSize = Storage::size($storagePath);
        }
        
        // Check if it's a video file
        $isVideo = str_starts_with($mimeType, 'video/') || 
                  $material->type === 'video' ||
                  str_ends_with(strtolower($material->file_name), '.mp4') ||
                  str_ends_with(strtolower($storagePath), '.mp4');
        
        // If we can't determine mime type but it should be video, set appropriate headers
        if ($isVideo && !str_starts_with($mimeType, 'video/')) {
            $mimeType = 'video/mp4';
        }
        
        // Base headers
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Cache-Control' => 'public, max-age=31536000',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range, Accept-Encoding',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
            'Accept-Ranges' => 'bytes',
        ];
        
        // Add video-specific headers for streaming support
        if ($isVideo) {
            $headers['Content-Disposition'] = 'inline'; // Play inline, not download
            
            // Handle Range requests for video seeking
            $rangeHeader = request()->header('Range');
            
            if ($rangeHeader && $fileSize > 0) {
                // Parse range header
                list($type, $range) = explode('=', $rangeHeader, 2);
                
                if ($type === 'bytes') {
                    list($start, $end) = explode('-', $range, 2);
                    
                    $start = intval($start);
                    $end = $end === '' || $end === null ? $fileSize - 1 : min(intval($end), $fileSize - 1);
                    
                    if ($start > $end || $start >= $fileSize) {
                        return response('', 416, [
                            'Content-Range' => 'bytes */' . $fileSize
                        ]);
                    }
                    
                    $length = $end - $start + 1;
                    
                    // Update headers for partial content
                    $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $fileSize;
                    $headers['Content-Length'] = $length;
                    $headers['HTTP/1.1'] = '206 Partial Content';
                    $statusCode = 206;
                    
                    \Log::info('Serving partial content', [
                        'start' => $start,
                        'end' => $end,
                        'length' => $length,
                        'range' => $rangeHeader
                    ]);
                    
                    // Stream partial content more efficiently
                    return Storage::response($storagePath, null, $headers, 'attachment', $statusCode);
                }
            }
        } else {
            // For non-video files (PDFs, documents)
            $headers['Content-Disposition'] = 'inline; filename="' . basename($material->file_name) . '"';
            
            // For PDF files, add additional headers for better browser support
            if ($mimeType === 'application/pdf') {
                $headers['Content-Type'] = 'application/pdf';
            }
        }
        
        \Log::info('Streaming file with headers', [
            'is_video' => $isVideo,
            'headers' => $headers,
            'status_code' => 200
        ]);
        
        // For full file requests (no range header or non-video files)
        // Use Laravel's built-in response for better performance
        return Storage::response($storagePath, null, $headers);
        
    } catch (\Exception $e) {
        \Log::error('Streaming error', [
            'material_id' => $material->id ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
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
        $liked = false;
    } else {
        // Use raw DB query to insert without timestamps
        DB::table('material_likes')->insert([
            'material_id' => $material->id,
            'user_id' => $user->id
        ]);
        $message = 'Material liked successfully';
        $liked = true;
    }

    // Refresh to get updated count
    $material->loadCount('likes');

    return response()->json([
        'success' => true,
        'message' => $message,
        'likes_count' => $material->likes_count,
        'liked' => $liked
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
   /**
 * Helper method to get the correct storage path
 */
private function getStoragePath(Material $material): string
{
    // If file_path is already set and exists, use it
    if (!empty($material->file_path)) {
        // Clean the path and check if it exists
        $cleanPath = ltrim($material->file_path, '/\\');
        
        // Try different variations of the path
        $possiblePaths = [
            $cleanPath,
            'public/' . $cleanPath,
            'storage/app/public/' . $cleanPath,
            'materials/' . basename($cleanPath),
            'public/materials/' . basename($cleanPath),
        ];
        
        foreach ($possiblePaths as $path) {
            if (Storage::exists($path)) {
                \Log::info('Found file at path', ['path' => $path, 'material_id' => $material->id]);
                return $path;
            }
        }
    }
    
    // Try with file_name if file_path didn't work
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
                return $path;
            }
        }
    }
    
    // Log error and return default path
    \Log::error('Could not find file for material', [
        'material_id' => $material->id,
        'file_path' => $material->file_path,
        'file_name' => $material->file_name
    ]);
    
    // Default to public/materials directory with the filename from file_path
    if (!empty($material->file_path)) {
        return 'public/materials/' . basename($material->file_path);
    }
    
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

/**
 * Check if a video format is browser-compatible
 */
private function isVideoBrowserCompatible($mimeType, $fileName)
{
    $compatibleMimeTypes = [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime', // MOV files
        'video/x-msvideo', // AVI files
        'video/x-matroska', // MKV files
    ];
    
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $compatibleExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    
    return in_array($mimeType, $compatibleMimeTypes) || in_array($extension, $compatibleExtensions);
}

/**
 * Get appropriate content type for video file
 */
private function getVideoContentType($fileName)
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $contentTypes = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'flv' => 'video/x-flv',
        'wmv' => 'video/x-ms-wmv',
    ];
    
    return $contentTypes[$extension] ?? 'video/mp4';
}


}