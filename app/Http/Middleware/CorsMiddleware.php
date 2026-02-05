<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight requests
        if ($request->getMethod() === "OPTIONS") {
            return response()->json('OK', 200, [
                'Access-Control-Allow-Origin' => 'http://localhost:5173',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        $response = $next($request);

        // Define CORS headers
        $corsHeaders = [
            'Access-Control-Allow-Origin' => 'http://localhost:5173',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Length, X-Filename',
        ];

        // Check for BinaryFileResponse (the missing case)
        if ($response instanceof BinaryFileResponse) {
            foreach ($corsHeaders as $key => $value) {
                $response->headers->set($key, $value);
            }
            return $response;
        }

        // Check if response is a StreamedResponse (file download/stream)
        if ($response instanceof StreamedResponse) {
            foreach ($corsHeaders as $key => $value) {
                $response->headers->set($key, $value);
            }
            return $response;
        }

        // For regular responses, use header() method
        foreach ($corsHeaders as $key => $value) {
            $response = $response->header($key, $value);
        }
        
        return $response;
    }
}