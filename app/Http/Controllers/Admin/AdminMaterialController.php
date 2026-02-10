<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Material;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminMaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::with(['department', 'createdBy']);
        
        // Apply filters
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }
        
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }
        
        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        
        if ($request->has('is_published')) {
            $query->where('is_published', $request->is_published);
        }
        
        // Sort
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($sort, $order);
        
        // Pagination
        $perPage = $request->get('per_page', 20);
        $materials = $query->paginate($perPage);
        
        return response()->json($materials);
    }
    
    /**
     * Store a new material
     */
    public function store(Request $request)
    {
        // Validate based on type
        $rules = [
            'department_id' => 'required|integer|exists:departments,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:video,document,youtube',
            'level' => 'required|integer|between:1,4',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'tags' => 'nullable|array',
            'pages' => 'nullable|integer|min:1',
            'author' => 'nullable|string|max:255',
            'is_published' => 'boolean'
        ];
        
        // Additional rules based on type
        if ($request->type === 'youtube') {
            $rules['youtube_url'] = 'required|string|url|max:500';
            $rules['thumbnail_url'] = 'nullable|string|url|max:500';
            $rules['duration'] = 'nullable|integer|min:1';
        } else {
            $rules['file'] = 'required|file|mimes:mp4,avi,mov,wmv,flv,pdf,doc,docx,pptx,txt,zip';
            
            if ($request->type === 'video') {
                $rules['file'] = 'required|file|mimes:mp4,avi,mov,wmv,flv|max:102400'; // 100MB max for videos
                $rules['duration'] = 'nullable|integer|min:1';
            } else {
                $rules['file'] = 'required|file|mimes:pdf,doc,docx,pptx,txt,zip|max:51200'; // 50MB max for documents
            }
        }
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = [
            'department_id' => $request->department_id,
            'created_by' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'level' => $request->level,
            'difficulty' => $request->difficulty,
            'tags' => $request->tags,
            'pages' => $request->pages,
            'author' => $request->author,
            'is_published' => $request->boolean('is_published', false)
        ];
        
        if ($request->type === 'youtube') {
            // Extract YouTube video ID from URL
            $youtubeId = Material::extractYoutubeId($request->youtube_url);
            
            if (!$youtubeId) {
                return response()->json([
                    'errors' => ['youtube_url' => ['Invalid YouTube URL']]
                ], 422);
            }
            
            $data['youtube_id'] = $youtubeId;
            $data['youtube_url'] = $request->youtube_url;
            $data['thumbnail_url'] = $request->thumbnail_url ?: "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg";
            $data['duration'] = $request->duration;
            $data['file_path'] = null;
            $data['file_name'] = null;
            $data['file_size'] = null;
        } else {
            // Handle file upload
            $file = $request->file('file');
            $path = $file->store('materials');
            
            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
            $data['duration'] = $request->duration;
            
            if ($request->type === 'video') {
                // You might want to extract duration from video file here
                // For now, use provided duration or default
                $data['duration'] = $request->duration ?: 0;
            }
            
            $data['youtube_id'] = null;
            $data['youtube_url'] = null;
            $data['thumbnail_url'] = null;
        }
        
        // Create material record
        $material = Material::create($data);
        
        return response()->json([
            'message' => 'Material created successfully',
            'material' => $material->load(['department', 'createdBy'])
        ], 201);
    }
    
    /**
     * Publish / Unpublish a material
     */
    public function publish(Material $material, Request $request)
    {
        $material->update([
            'is_published' => $request->boolean('publish', true)
        ]);
        
        return response()->json([
            'message' => $request->boolean('publish', true) ? 'Material published' : 'Material unpublished',
            'material' => $material->load(['department', 'createdBy'])
        ]);
    }
    
    /**
     * Update an existing material
     */
    public function update(Request $request, Material $material)
    {
        // Validation rules
        $rules = [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|string|in:video,document,youtube',
            'level' => 'sometimes|required|integer|between:1,4',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'tags' => 'nullable|array',
            'pages' => 'nullable|integer|min:1',
            'author' => 'nullable|string|max:255',
            'is_published' => 'boolean'
        ];
        
        // Additional rules based on type
        if ($request->type === 'youtube' || $material->type === 'youtube') {
            $rules['youtube_url'] = 'required|string|url|max:500';
            $rules['thumbnail_url'] = 'nullable|string|url|max:500';
            $rules['duration'] = 'nullable|integer|min:1';
        } else {
            $rules['file'] = 'sometimes|file';
            
            if ($request->type === 'video' || $material->type === 'video') {
                $rules['file'] = 'sometimes|file|mimes:mp4,avi,mov,wmv,flv|max:102400';
                $rules['duration'] = 'nullable|integer|min:1';
            } else {
                $rules['file'] = 'sometimes|file|mimes:pdf,doc,docx,pptx,txt,zip|max:51200';
            }
        }
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = [
            'title' => $request->input('title', $material->title),
            'description' => $request->input('description', $material->description),
            'type' => $request->input('type', $material->type),
            'level' => $request->input('level', $material->level),
            'difficulty' => $request->input('difficulty', $material->difficulty),
            'tags' => $request->input('tags', $material->tags),
            'pages' => $request->input('pages', $material->pages),
            'author' => $request->input('author', $material->author),
            'is_published' => $request->boolean('is_published', $material->is_published)
        ];
        
        // Handle type change or updates
        if ($request->has('type') && $request->type !== $material->type) {
            // Type is changing
            if ($request->type === 'youtube') {
                // Changing to YouTube
                if ($material->file_path && Storage::exists($material->file_path)) {
                    Storage::delete($material->file_path);
                }
                
                $youtubeId = Material::extractYoutubeId($request->youtube_url);
                
                if (!$youtubeId) {
                    return response()->json([
                        'errors' => ['youtube_url' => ['Invalid YouTube URL']]
                    ], 422);
                }
                
                $data['youtube_id'] = $youtubeId;
                $data['youtube_url'] = $request->youtube_url;
                $data['thumbnail_url'] = $request->thumbnail_url ?: "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg";
                $data['file_path'] = null;
                $data['file_name'] = null;
                $data['file_size'] = null;
                $data['duration'] = $request->duration;
            } else {
                // Changing to file upload
                $data['youtube_id'] = null;
                $data['youtube_url'] = null;
                $data['thumbnail_url'] = null;
                
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $data['file_path'] = $file->store('materials');
                    $data['file_name'] = $file->getClientOriginalName();
                    $data['file_size'] = $file->getSize();
                    
                    if ($request->type === 'video') {
                        $data['duration'] = $request->duration ?: 0;
                    }
                } else {
                    // Keep existing file if no new file provided
                    $data['file_path'] = $material->file_path;
                    $data['file_name'] = $material->file_name;
                    $data['file_size'] = $material->file_size;
                }
            }
        } else {
            // Same type, update within type
            if ($material->type === 'youtube') {
                // Update YouTube details
                if ($request->has('youtube_url')) {
                    $youtubeId = Material::extractYoutubeId($request->youtube_url);
                    
                    if (!$youtubeId) {
                        return response()->json([
                            'errors' => ['youtube_url' => ['Invalid YouTube URL']]
                        ], 422);
                    }
                    
                    $data['youtube_id'] = $youtubeId;
                    $data['youtube_url'] = $request->youtube_url;
                    $data['thumbnail_url'] = $request->thumbnail_url ?: "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg";
                }
                
                if ($request->has('duration')) {
                    $data['duration'] = $request->duration;
                }
            } else {
                // Update file-based material
                if ($request->hasFile('file')) {
                    // Delete old file
                    if ($material->file_path && Storage::exists($material->file_path)) {
                        Storage::delete($material->file_path);
                    }
                    
                    $file = $request->file('file');
                    $data['file_path'] = $file->store('materials');
                    $data['file_name'] = $file->getClientOriginalName();
                    $data['file_size'] = $file->getSize();
                }
                
                if ($request->has('duration') && $material->type === 'video') {
                    $data['duration'] = $request->duration;
                }
            }
        }
        
        $material->update($data);
        
        return response()->json([
            'message' => 'Material updated successfully',
            'material' => $material->load(['department', 'createdBy'])
        ]);
    }
    
    /**
     * Delete a material
     */
    public function destroy(Material $material)
    {
        // Delete file from storage if it's not a YouTube video
        if ($material->type !== 'youtube' && $material->file_path && Storage::exists($material->file_path)) {
            Storage::delete($material->file_path);
        }
        
        $material->delete();
        
        return response()->json([
            'message' => 'Material deleted successfully'
        ]);
    }
    
    /**
     * Increment view count
     */
    public function incrementViews(Material $material)
    {
        $material->safeIncrement('views_count');
        
        return response()->json([
            'message' => 'View count incremented',
            'views_count' => $material->fresh()->views_count
        ]);
    }
    
    /**
     * Increment download count
     */
    public function incrementDownloads(Material $material)
    {
        $material->safeIncrement('download_count');
        
        return response()->json([
            'message' => 'Download count incremented',
            'download_count' => $material->fresh()->download_count
        ]);
    }
    
    /**
     * Get material statistics
     */
    public function statistics()
    {
        $stats = [
            'total' => Material::count(),
            'youtube' => Material::where('type', 'youtube')->count(),
            'video' => Material::where('type', 'video')->count(),
            'document' => Material::where('type', 'document')->count(),
            'published' => Material::where('is_published', true)->count(),
            'total_views' => Material::sum('views_count'),
            'total_downloads' => Material::sum('download_count'),
            'by_level' => Material::groupBy('level')->selectRaw('level, count(*) as count')->get(),
            'by_department' => Material::with('department')
                ->groupBy('department_id')
                ->selectRaw('department_id, count(*) as count')
                ->get()
        ];
        
        return response()->json($stats);
    }
}