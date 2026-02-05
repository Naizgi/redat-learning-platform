<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    /**
     * Stream video file
     */
    public function stream($filename, Request $request)
    {
        // Enable detailed logging
        \Log::info('=== VIDEO STREAM REQUEST ===');
        \Log::info('Filename:', ['filename' => $filename]);
        \Log::info('Request IP:', ['ip' => $request->ip()]);
        \Log::info('User Agent:', ['ua' => $request->userAgent()]);
        \Log::info('Headers:', ['headers' => $request->headers->all()]);
        \Log::info('Method:', ['method' => $request->method()]);
        
        // Clean filename
        $filename = basename($filename);
        \Log::info('Cleaned filename:', ['filename' => $filename]);
        
        // Try multiple possible storage locations
        $possiblePaths = [
            'materials/' . $filename,           // materials/uApO0dfwZrqWajc10pPh2NGOjYetI74IUcyMtYM4.mp4
            'public/materials/' . $filename,    // public/materials/uApO0dfwZrqWajc10pPh2NGOjYetI74IUcyMtYM4.mp4
            'videos/' . $filename,              // videos/uApO0dfwZrqWajc10pPh2NGOjYetI74IUcyMtYM4.mp4
            'public/videos/' . $filename,       // public/videos/uApO0dfwZrqWajc10pPh2NGOjYetI74IUcyMtYM4.mp4
            $filename,                          // uApO0dfwZrqWajc10pPh2NGOjYetI74IUcyMtYM4.mp4
        ];
        
        $foundPath = null;
        foreach ($possiblePaths as $path) {
            if (Storage::exists($path)) {
                $foundPath = $path;
                \Log::info('Found file at path:', ['path' => $path]);
                break;
            }
            \Log::info('File not found at path:', ['path' => $path]);
        }
        
        if (!$foundPath) {
            \Log::error('Video file not found in any location:', [
                'filename' => $filename,
                'checked_paths' => $possiblePaths
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Video file not found',
                'filename' => $filename,
                'checked_paths' => $possiblePaths
            ], 404);
        }
        
        // Get file information
        $filePath = Storage::path($foundPath);
        $fileSize = Storage::size($foundPath);
        $mimeType = Storage::mimeType($foundPath);
        
        \Log::info('File details:', [
            'found_path' => $foundPath,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'file_exists' => file_exists($filePath)
        ]);
        
        // Set CORS headers
        $headers = [
            'Content-Type' => $mimeType ?: 'video/mp4',
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Range, Accept-Encoding',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range',
            'Access-Control-Max-Age' => '86400',
        ];
        
        // Handle OPTIONS request (preflight)
        if ($request->isMethod('OPTIONS')) {
            \Log::info('Handling OPTIONS preflight request');
            return response()->json([], 200, $headers);
        }
        
        // Handle Range requests for video seeking
        if ($request->headers->has('Range')) {
            $range = $request->header('Range');
            \Log::info('Range request received:', ['range' => $range]);
            
            return $this->handleRangeRequest($filePath, $fileSize, $headers, $range);
        }
        
        // Serve full file
        \Log::info('Serving full video file');
        return response()->file($filePath, $headers);
    }
    
    /**
     * Handle HTTP Range requests for video seeking
     */
    private function handleRangeRequest($filePath, $fileSize, $headers, $range)
    {
        list($sizeUnit, $rangeData) = explode('=', $range, 2);
        
        if ($sizeUnit !== 'bytes') {
            return response('', 416, $headers);
        }
        
        list($start, $end) = explode('-', $rangeData, 2);
        $start = intval($start);
        $end = $end === '' || $end === null ? $fileSize - 1 : min(intval($end), $fileSize - 1);
        
        if ($start > $end || $start >= $fileSize) {
            return response('', 416, [
                'Content-Range' => 'bytes */' . $fileSize
            ]);
        }
        
        $length = $end - $start + 1;
        
        $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $fileSize;
        $headers['Content-Length'] = $length;
        
        \Log::info('Serving partial content:', [
            'start' => $start,
            'end' => $end,
            'length' => $length
        ]);
        
        return new StreamedResponse(function () use ($filePath, $start, $length) {
            $stream = fopen($filePath, 'rb');
            fseek($stream, $start);
            
            $bytesToRead = $length;
            $chunkSize = 8192;
            
            while ($bytesToRead > 0 && !feof($stream)) {
                $currentChunkSize = min($chunkSize, $bytesToRead);
                echo fread($stream, $currentChunkSize);
                $bytesToRead -= $currentChunkSize;
                flush();
            }
            
            fclose($stream);
        }, 206, $headers);
    }
    
    /**
     * Test endpoint to check if video file exists
     */
    public function testExists($filename)
    {
        $filename = basename($filename);
        
        $possiblePaths = [
            'materials/' . $filename,
            'public/materials/' . $filename,
            'videos/' . $filename,
            'public/videos/' . $filename,
            $filename,
        ];
        
        $results = [];
        foreach ($possiblePaths as $path) {
            $exists = Storage::exists($path);
            $results[$path] = [
                'exists' => $exists,
                'size' => $exists ? Storage::size($path) : 0,
                'mime_type' => $exists ? Storage::mimeType($path) : null,
            ];
        }
        
        return response()->json([
            'filename' => $filename,
            'results' => $results,
            'storage_disk' => config('filesystems.default'),
            'storage_root' => Storage::path('')
        ]);
    }
}