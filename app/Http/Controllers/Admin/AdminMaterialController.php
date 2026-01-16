<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Material;
use Illuminate\Support\Facades\Storage;

class AdminMaterialController extends Controller
{


    // Add this method to your MaterialController
    public function index(Request $request)
     {
     $query = Material::with(['department', 'createdBy']);
    
    // Apply filters
     if ($request->has('search')) {
        $query->where('title', 'like', '%' . $request->search . '%');
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
    
     if ($request->has('is_published')) {
        $query->where('is_published', $request->is_published);
     }
    
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
        // Validate the request
        $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:video,document',
            'file' => 'required|file|mimes:mp4,pdf,docx,pptx',
            'level' => 'required|integer|between:1,4',
        ]);

        // Store the uploaded file in Laravel storage
        $path = $request->file('file')->store('materials');

        // Create material record
        $material = Material::create([
            'department_id' => $request->department_id,
            'created_by' => auth()->id(),
            'title' => $request->title,
            'type' => $request->type,
            'file_path' => $path,
            'level' => $request->level,
            'is_published' => false
        ]);

        return response()->json([
            'message' => 'Material created successfully',
            'material' => $material
        ], 201);
    }

    /**
     * Publish / Unpublish a material
     */
    public function publish(Material $material, Request $request)
    {
        $material->update([
            'is_published' => $request->input('publish', true)
        ]);

        return response()->json([
            'message' => $request->input('publish', true) ? 'Material published' : 'Material unpublished',
            'material' => $material
        ]);
    }

    /**
     * Update an existing material
     */
    public function update(Request $request, Material $material)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:video,document',
            'file' => 'sometimes|file|mimes:mp4,pdf,docx,pptx',
            'level' => 'sometimes|required|integer|between:1,4',
        ]);

        // If a new file is uploaded, store it and delete old one
        if ($request->hasFile('file')) {
            // Delete old file
            if ($material->file_path && Storage::exists($material->file_path)) {
                Storage::delete($material->file_path);
            }
            $material->file_path = $request->file('file')->store('materials');
        }

        // Update other fields
        $material->title = $request->input('title', $material->title);
        $material->type = $request->input('type', $material->type);
        $material->level = $request->input('level', $material->level);

        $material->save();

        return response()->json([
            'message' => 'Material updated successfully',
            'material' => $material
        ]);
    }

    /**
     * Delete a material
     */
    public function destroy(Material $material)
    {
        // Delete file from storage
        if ($material->file_path && Storage::exists($material->file_path)) {
            Storage::delete($material->file_path);
        }

        $material->delete();

        return response()->json([
            'message' => 'Material deleted successfully'
        ]);
    }
}
