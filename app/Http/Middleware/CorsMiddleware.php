<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

        // Handle Symfony responses (BinaryFileResponse, StreamedResponse, etc.)
        if ($response instanceof SymfonyResponse) {
            foreach ($corsHeaders as $key => $value) {
                $response->headers->set($key, $value);
            }
            return $response;
        }

        // For regular Laravel responses (that have header() method)
        if (method_exists($response, 'header')) {
            foreach ($corsHeaders as $key => $value) {
                $response = $response->header($key, $value);
            }
            return $response;
        }

        // Fallback for any other response type
        return $response;
    }
}