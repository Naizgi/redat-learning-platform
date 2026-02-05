<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoController extends Controller
{
    public function stream($filename)
    {
        $filePath = 'materials/' . $filename;
        
        // Check if file exists
        if (!Storage::disk('public')->exists($filePath)) {
            abort(404, 'Video not found');
        }
        
        $fullPath = Storage::disk('public')->path($filePath);
        
        // Create a BinaryFileResponse (this works with range requests)
        $response = new BinaryFileResponse($fullPath);
        
        // Set headers
        BinaryFileResponse::trustXSendfileTypeHeader();
        
        $response->headers->set('Content-Type', 'video/mp4');
        $response->headers->set('Content-Length', filesize($fullPath));
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Cache-Control', 'public, max-age=31536000');
        
        // Handle range requests (for seeking)
        $response->setAutoEtag();
        $response->setAutoLastModified();
        
        return $response;
    }
}