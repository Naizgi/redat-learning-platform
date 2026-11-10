<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorsMiddleware
{
    /**
     * Whitelist of frontend origins allowed to call the API.
     */
    protected array $allowedOrigins = [
        // Local dev
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:4173',
        'http://127.0.0.1:4173',

        // Production
        'https://redatlearninghub.com',
        'https://www.redatlearninghub.com',
        'https://api.redatlearninghub.com',
    ];

    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin');

        // Determine the value to echo back
        $allowOrigin = $this->resolveAllowedOrigin($origin);

        // Common CORS headers used on all responses
        $corsHeaders = [
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Expose-Headers'    => 'Content-Disposition, Content-Length, X-Filename',
            'Vary'                             => 'Origin',
        ];

        if ($allowOrigin) {
            $corsHeaders['Access-Control-Allow-Origin'] = $allowOrigin;
        }

        // -------- Handle preflight (OPTIONS) --------
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json('OK', 200, $corsHeaders);
        }

        $response = $next($request);

        // -------- Attach headers to all response types --------
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            foreach ($corsHeaders as $key => $value) {
                $response->headers->set($key, $value);
            }
            return $response;
        }

        foreach ($corsHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * Return the allowed origin for the given request Origin, or null.
     */
    private function resolveAllowedOrigin(?string $origin): ?string
    {
        if (!$origin) {
            return null;
        }

        // Exact match
        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        // Optional wildcard subdomain support
        $wildcards = [
            '/^https:\/\/([a-z0-9-]+\.)?redatlearninghub\.com$/i',
            '/^https:\/\/([a-z0-9-]+\.)?netlify\.app$/i',
            '/^https:\/\/([a-z0-9-]+\.)?vercel\.app$/i',
        ];

        foreach ($wildcards as $pattern) {
            if (preg_match($pattern, $origin)) {
                return $origin;
            }
        }

        return null;
    }
}