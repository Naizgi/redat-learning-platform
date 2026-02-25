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


/**
 * Get featured materials for public/home page
 */
public function getFeatured(Request $request)
{
    try {
        \Log::info('Featured materials requested', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'public_access' => true
        ]);

        // Get all active departments
        $departments = \App\Models\Department::all();
        
        $featuredMaterials = collect();
        
        // If no departments, get materials directly
        if ($departments->isEmpty()) {
            $featuredMaterials = Material::where('is_published', true)
                ->with(['department'])
                ->withCount(['likes', 'comments'])
                ->orderByDesc('views_count')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();
        } else {
            // Get a few materials from each department for display
            foreach ($departments as $department) {
                // Get 2 materials from each department
                $materials = Material::where('is_published', true)
                    ->where('department_id', $department->id)
                    ->with(['department'])
                    ->withCount(['likes', 'comments'])
                    ->orderByDesc('views_count')
                    ->orderByDesc('created_at')
                    ->limit(2)
                    ->get();
                    
                // Add file URLs
                $materials->transform(function ($material) {
                    $material->file_url = $this->getFileUrl($material);
                    $material->download_url = $this->getDownloadUrl($material);
                    return $material;
                });
                
                $featuredMaterials = $featuredMaterials->merge($materials);
            }
            
            // If no materials from departments, get some general materials
            if ($featuredMaterials->isEmpty()) {
                $featuredMaterials = Material::where('is_published', true)
                    ->with(['department'])
                    ->withCount(['likes', 'comments'])
                    ->orderByDesc('views_count')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get();
            }
        }
        
        // Add file URLs to materials
        $featuredMaterials->transform(function ($material) {
            $material->file_url = $this->getFileUrl($material);
            $material->download_url = $this->getDownloadUrl($material);
            return $material;
        });
        
        // Limit to 8 materials total for the featured section
        $featuredMaterials = $featuredMaterials->take(8);
        
        return response()->json([
            'success' => true,
            'data' => $featuredMaterials,
            'count' => $featuredMaterials->count(),
            'message' => 'Featured materials retrieved successfully'
        ]);
        
    } catch (\Throwable $e) {
        \Log::error('Featured materials error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving featured materials',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
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
        
        // Get storage path
        $storagePath = $this->getStoragePath($material);
        \Log::info('Storage path from helper: ' . $storagePath);
        
        // Check full system path
        $fullPath = storage_path('app/' . $storagePath);
        \Log::info('Full system path: ' . $fullPath);
        \Log::info('File exists check: ' . (file_exists($fullPath) ? 'YES' : 'NO'));
        \Log::info('File readable: ' . (is_readable($fullPath) ? 'YES' : 'NO'));
        \Log::info('File size: ' . (file_exists($fullPath) ? filesize($fullPath) : 'N/A'));
        
        // Check using Storage facade
        $storageExists = Storage::exists($storagePath);
        \Log::info('Storage::exists result: ' . ($storageExists ? 'YES' : 'NO'));
        
        if (!$storageExists) {
            \Log::error('File not found in storage');
            
            // Try to find the file by searching
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
                    'debug' => [
                        'file_name' => $material->file_name,
                        'file_path' => $material->file_path,
                        'storage_path' => $storagePath,
                        'full_path' => $fullPath,
                        'public_url' => asset('storage/materials/' . urlencode($material->file_name))
                    ]
                ], 404);
            }
        }
        
        // Increment view count
        $material->increment('views_count');
        
        // Get file details
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
        
        // Prepare headers
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
            'Access-Control-Allow-Origin' => '*',
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

/**
 * Get comments for a material
 */
public function getComments(Material $material)
{
    try {
        // Optionally authorize access if needed
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
            'data' => $comments,
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
 * Helper method to get the correct storage path - UPDATED WITH BETTER LOGGING
 */
private function getStoragePath(Material $material): string
{
    \Log::info('getStoragePath called for material ID: ' . $material->id);
    
    // If file_path is already set and exists, use it
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
                
                // Log the public URL for debugging
                $publicUrl = asset('storage/materials/' . urlencode($material->file_name));
                \Log::info('Public URL would be', ['url' => $publicUrl, 'material_id' => $material->id]);
                
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
    
    if (!empty($material->file_name)) {
        return 'public/materials/' . $material->file_name;
    }
    
    return 'public/materials/unknown';
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