<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Get the user's active subscription
        $subscription = $user->subscription;

        // Check if subscription exists and is active
        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'message' => 'Subscription expired or inactive',
            ], 403);
        }

        return $next($request);
    }
}
