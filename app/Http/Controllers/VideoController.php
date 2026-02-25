<?php

namespace App\Http\Controllers;

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
    
    // Create a BinaryFileResponse
    $response = new BinaryFileResponse($fullPath);
    
    // Set headers including CORS
    BinaryFileResponse::trustXSendfileTypeHeader();
    
    $response->headers->set('Content-Type', 'video/mp4');
    $response->headers->set('Content-Length', filesize($fullPath));
    $response->headers->set('Accept-Ranges', 'bytes');
    $response->headers->set('Cache-Control', 'public, max-age=31536000');
    
    // Add CORS headers
    $response->headers->set('Access-Control-Allow-Origin', 'https://redatlearninghub.com');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Range, Content-Type');
    $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Range, Accept-Ranges');
    
    // Handle range requests
    $response->setAutoEtag();
    $response->setAutoLastModified();
    
    return $response;
}
}