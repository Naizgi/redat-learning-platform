<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // max 100MB
        ]);

        $file = $request->file('file');

        // Store in storage/app/public/uploads
        $path = $file->store('uploads', 'public');

        return response()->json([
            'success' => true,
            'file_path' => Storage::url($path),
        ]);
    }
}
