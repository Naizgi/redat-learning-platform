<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    public function stream($filename, Request $request)
    {
        $path = storage_path('app/public/videos/' . $filename);

        if (!file_exists($path)) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $size = filesize($path);
        $start = 0;
        $length = $size;
        $end = $size - 1;

        if ($request->headers->has('Range')) {
            $range = $request->header('Range');
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);

            $start = intval($matches[1]);
            $end = $matches[2] !== '' ? intval($matches[2]) : $end;
            $length = $end - $start + 1;

            $headers = [
                'Content-Type' => 'video/mp4',
                'Content-Length' => $length,
                'Content-Range' => "bytes $start-$end/$size",
                'Accept-Ranges' => 'bytes',
            ];

            $response = new StreamedResponse(function () use ($path, $start, $length) {
                $stream = fopen($path, 'rb');
                fseek($stream, $start);
                echo fread($stream, $length);
                fclose($stream);
            }, 206, $headers);

            return $response;
        }

        return response()->file($path);
    }
}
