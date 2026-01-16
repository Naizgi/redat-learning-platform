<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Material;
use App\Models\Subscription;
use App\Models\Department;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            // Remove the long timeout for now
            set_time_limit(30); // 30 seconds should be plenty
            
            // Use caching - cache results for 5 minutes
            $stats = Cache::remember('admin_dashboard_stats_v2', 300, function () {
                return $this->calculateStats();
            });
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard stats error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'error' => 'Failed to fetch dashboard stats',
                'message' => $e->getMessage(),
                'debug' => env('APP_DEBUG') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }
    
    protected function calculateStats()
    {
        // Use DB raw queries for maximum performance
        // These should be very fast even with large datasets
        
        try {
            // Count users - simple count
            $totalUsers = DB::table('users')->count();
            
            // Count materials - simple count
            $totalCourses = DB::table('materials')->count();
            
            // Count departments - simple count
            $totalDepartments = DB::table('departments')->count();
            
            // Subscription stats - simple counts
            $subscriptionStats = DB::table('subscriptions')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
                ')
                ->first();
            
            // Return simple array
            return [
                'total_users' => (int) $totalUsers,
                'total_courses' => (int) $totalCourses,
                'total_departments' => (int) $totalDepartments,
                'subscriptions' => [
                    'total' => (int) ($subscriptionStats->total ?? 0),
                    'active' => (int) ($subscriptionStats->active ?? 0),
                    'pending' => (int) ($subscriptionStats->pending ?? 0),
                ],
                'cached_at' => now()->toDateTimeString(),
                'cache_ttl' => '5 minutes'
            ];
            
        } catch (\Exception $e) {
            \Log::error('Dashboard calculateStats error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Super simple backup method - counts only
     */
    public function statsSimple()
    {
        try {
            set_time_limit(10); // Very short timeout
            
            return response()->json([
                'total_users' => User::count(),
                'total_courses' => Material::count(),
                'total_departments' => Department::count(),
                'subscriptions' => [
                    'total' => Subscription::count(),
                    'active' => Subscription::where('status', 'active')->count(),
                    'pending' => Subscription::where('status', 'pending')->count(),
                ],
                'generated_at' => now()->toDateTimeString(),
                'note' => 'Simple counts - no caching'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard simple stats error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch simple stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}