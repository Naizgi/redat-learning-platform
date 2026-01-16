<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Progress;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function update(Request $request, Material $material)
    {
        // Validate the request
        $request->validate([
            'progress' => 'required|numeric|min:0|max:100',
        ]);
        
        $progress = Progress::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'material_id' => $material->id,
            ],
            [
                'progress' => $request->progress,
                'completed' => $request->progress >= 100,
                'time_spent' => $request->time_spent ?? 0, // Optional
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress updated successfully',
            'data' => $progress
        ]);
    }
    
    // You might want to add this method to get user's progress
    public function index(Request $request)
    {
        $progress = Progress::where('user_id', auth()->id())
            ->with('material')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $progress
        ]);
    }
}