<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getProfile(Request $request)
    {
        try {
            Log::info('ProfileController: getProfile called');
            
            $user = $request->user();
            
            if (!$user) {
                Log::error('ProfileController: No authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            Log::info('ProfileController: User found', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]);

            // Return only basic user data without any relationships
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '',
                    'address' => $user->address ?? '',
                    'bio' => $user->bio ?? '',
                    'role' => $user->role ?? 'student',
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('ProfileController error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStatistics(Request $request)
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_materials' => 0,
                    'total_videos' => 0,
                    'completed_materials' => 0,
                    'in_progress_materials' => 0,
                    'total_time_spent' => 0,
                    'total_time_spent_formatted' => '0 hours',
                    'join_date' => $user->created_at,
                    'last_active' => $user->updated_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Statistics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Update profile endpoint not implemented yet'
        ], 501);
    }

    public function changePassword(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Change password endpoint not implemented yet'
        ], 501);
    }

    public function uploadAvatar(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Upload avatar endpoint not implemented yet'
        ], 501);
    }

    public function updatePreferences(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Update preferences endpoint not implemented yet'
        ], 501);
    }

    public function getActivity(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Get activity endpoint not implemented yet'
        ], 501);
    }

    private function formatTimeSpent($seconds)
    {
        return '0 hours';
    }
}