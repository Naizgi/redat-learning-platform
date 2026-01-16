<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Check if user has any of the required roles
        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Unauthorized. Required role: ' . implode(', ', $roles)], 403);
        }
        
        return $next($request);
    }
}