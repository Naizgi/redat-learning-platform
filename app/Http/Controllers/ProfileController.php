<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Material;
use App\Models\Progress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Constructor - apply middleware
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get the authenticated user's profile
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user()->load('department');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '',
                    'address' => $user->address ?? '',
                    'bio' => $user->bio ?? '',
                    'department_id' => $user->department_id,
                    'department' => $user->department ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                    ] : null,
                    'level' => $user->level ?? '',
                    'role' => $user->role,
                    'avatar' => $user->avatar_url,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'last_login' => $user->last_login_at ?? $user->updated_at,
                    'is_active' => $user->is_active,
                    'preferences' => $user->preferences,
                ],
                'message' => 'Profile retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching profile: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the authenticated user's profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => [
                    'sometimes',
                    'email',
                    Rule::unique('users')->ignore($user->id),
                ],
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'bio' => 'nullable|string|max:1000',
                'department_id' => 'nullable|exists:departments,id',
                'level' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $updateData = $request->only([
                'name', 'email', 'phone', 'address', 'bio', 
                'department_id', 'level'
            ]);

            // Remove null values
            $updateData = array_filter($updateData, function ($value) {
                return !is_null($value);
            });

            $user->update($updateData);
            $user->load('department');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'bio' => $user->bio,
                    'department' => $user->department ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                    ] : null,
                    'level' => $user->level,
                    'avatar' => $user->avatar_url,
                ],
                'message' => 'Profile updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|different:current_password',
                'confirm_password' => 'required|string|same:new_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The provided password does not match our records.']
                    ]
                ], 401);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error changing password: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload user avatar
     */
    /**
 * Upload user avatar
 */
/**
 * Upload user avatar
 */
public function uploadAvatar(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Log request details
        \Log::info('Avatar upload attempt', [
            'user_id' => $user->id,
            'has_file' => $request->hasFile('avatar'),
            'content_type' => $request->header('Content-Type')
        ]);

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $file = $request->file('avatar');
        
        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Uploaded file is not valid'
            ], 400);
        }

        // Delete old avatar if exists - FIXED: Don't use str_replace
        if ($user->avatar) {
            $oldPath = $user->avatar; // This is already the stored path
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
                \Log::info('Deleted old avatar', ['path' => $oldPath]);
            }
        }

        // Generate filename
        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store file
        $path = $file->storeAs('avatars', $filename, 'public');
        
        if (!$path) {
            throw new \Exception('Failed to store file');
        }

        \Log::info('Avatar stored', ['path' => $path]);

        // Update user record - store the path directly
        $user->avatar = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'data' => [
                'avatar_url' => asset('storage/' . $path),
            ],
            'message' => 'Avatar uploaded successfully'
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Avatar upload error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to upload avatar: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Get user statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $user = $request->user();
            
            $totalMaterials = Material::count();
            $totalVideos = Material::where('type', 'video')->count();
            
            $completedMaterials = Progress::where('user_id', $user->id)
                ->where('is_completed', true)
                ->count();
            
            $totalTimeSpent = Progress::where('user_id', $user->id)
                ->sum('time_spent_seconds') ?? 0;
            
            $inProgressMaterials = Progress::where('user_id', $user->id)
                ->where('is_completed', false)
                ->where('progress_percentage', '>', 0)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_materials' => $totalMaterials,
                    'total_videos' => $totalVideos,
                    'completed_materials' => $completedMaterials,
                    'in_progress_materials' => $inProgressMaterials,
                    'total_time_spent' => $totalTimeSpent,
                    'total_time_spent_formatted' => $this->formatTimeSpent($totalTimeSpent),
                    'join_date' => $user->created_at,
                    'last_active' => $user->updated_at,
                ],
                'message' => 'Statistics retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'email_notifications' => 'sometimes|boolean',
                'dark_mode' => 'sometimes|boolean',
                'language' => 'sometimes|string|in:en,am',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $currentPrefs = $user->preferences;
            $newPrefs = array_merge($currentPrefs, $request->only([
                'email_notifications', 'dark_mode', 'language'
            ]));

            $user->preferences = $newPrefs;
            $user->save();

            return response()->json([
                'success' => true,
                'data' => $newPrefs,
                'message' => 'Preferences updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating preferences: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activity
     */
    public function getActivity(Request $request)
    {
        try {
            $user = $request->user();
            
            $progress = Progress::where('user_id', $user->id)
                ->with('material')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get();

            $activities = $progress->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => 'progress',
                    'material_id' => $item->material_id,
                    'material_title' => $item->material->title ?? 'Unknown Material',
                    'progress' => $item->progress_percentage ?? 0,
                    'timestamp' => $item->updated_at,
                    'message' => "Progress updated to {$item->progress_percentage}% on {$item->material->title}"
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $activities,
                'message' => 'Activity retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching activity: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to format time spent
     */
    private function formatTimeSpent($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }
        
        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes . ' minutes';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes > 0) {
            return $hours . ' hours ' . $remainingMinutes . ' minutes';
        }
        
        return $hours . ' hours';
    }
}