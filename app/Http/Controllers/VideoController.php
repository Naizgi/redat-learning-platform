<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    public function stream($filename, Request $request)
    {
        // Debug: Log the request
        \Log::info('Video stream request', [
            'filename' => $filename,
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'method' => $request->method()
        ]);
        
        // Clean filename to prevent directory traversal
        $filename = basename($filename);
        
        \Log::info('Cleaned filename', ['filename' => $filename]);
        
        // Try multiple possible locations
        $possiblePaths = [
            storage_path('app/public/materials/' . $filename),
            storage_path('app/public/videos/' . $filename),
            storage_path('app/public/' . $filename),
            public_path('storage/materials/' . $filename),
            public_path('storage/videos/' . $filename),
            public_path('videos/' . $filename),
        ];
        
        $path = null;
        foreach ($possiblePaths as $possiblePath) {
            \Log::info('Checking path', ['path' => $possiblePath, 'exists' => file_exists($possiblePath)]);
            if (file_exists($possiblePath)) {
                $path = $possiblePath;
                \Log::info('Found file at path', ['path' => $path]);
                break;
            }
        }
        
        if (!$path || !file_exists($path)) {
            \Log::error('Video file not found', [
                'filename' => $filename,
                'checked_paths' => $possiblePaths
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Video file not found',
                'filename' => $filename
            ], 404);
        }

        $size = filesize($path);
        
        // Set headers
        $headers = [
            'Content-Type' => $this->getMimeType($filename),
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Range',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range',
        ];

        // Handle Range requests for video seeking
        if ($request->headers->has('Range')) {
            $range = $request->header('Range');
            \Log::info('Range request', ['range' => $range]);
            
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $size - 1;
                
                if ($start >= $size || $end >= $size || $start > $end) {
                    \Log::warning('Invalid range request', [
                        'start' => $start,
                        'end' => $end,
                        'size' => $size
                    ]);
                    
                    return response('', 416, [
                        'Content-Range' => 'bytes */' . $size
                    ]);
                }
                
                $length = $end - $start + 1;
                
                $headers['Content-Range'] = "bytes $start-$end/$size";
                $headers['Content-Length'] = $length;
                
                \Log::info('Serving partial content', [
                    'start' => $start,
                    'end' => $end,
                    'length' => $length
                ]);
                
                return new StreamedResponse(function () use ($path, $start, $length) {
                    $stream = fopen($path, 'rb');
                    fseek($stream, $start);
                    
                    $bytesToRead = $length;
                    while ($bytesToRead > 0 && !feof($stream)) {
                        $chunkSize = min(8192, $bytesToRead);
                        echo fread($stream, $chunkSize);
                        $bytesToRead -= $chunkSize;
                        flush();
                    }
                    
                    fclose($stream);
                }, 206, $headers);
            }
        }

        \Log::info('Serving full video file', [
            'path' => $path,
            'size' => $size,
            'headers' => $headers
        ]);

        // For full file requests
        return response()->file($path, $headers);
    }
    
    private function getMimeType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
        ];
        
        return $mimeTypes[$extension] ?? 'video/mp4';
    }
}