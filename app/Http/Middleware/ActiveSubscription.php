<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
 public function handle($request, Closure $next)
{
    $subscription = $request->user()->subscription;

    if (!$subscription || !$subscription->isActive()) {
        return response()->json([
            'message' => 'Subscription expired or inactive',
        ], 403);
    }

    return $next($request);
}

}
