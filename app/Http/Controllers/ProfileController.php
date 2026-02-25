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
            $user = $request->user();
            
            if (!$user) {
                Log::warning('Profile access attempt with no authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Try to load department relationship if it exists
            try {
                if (method_exists($user, 'department')) {
                    $user->load('department');
                }
            } catch (\Exception $e) {
                Log::warning('Could not load department relationship: ' . $e->getMessage());
                // Continue without department
            }

            // Get avatar URL safely
            $avatarUrl = null;
            try {
                $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : null;
            } catch (\Exception $e) {
                Log::warning('Could not generate avatar URL: ' . $e->getMessage());
            }

            // Get preferences safely
            $preferences = [
                'email_notifications' => true,
                'dark_mode' => false,
                'language' => 'en'
            ];
            
            try {
                if ($user->preferences) {
                    $preferences = array_merge($preferences, 
                        is_array($user->preferences) ? $user->preferences : json_decode($user->preferences, true)
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Could not parse preferences: ' . $e->getMessage());
            }

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
                    'avatar' => $avatarUrl,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'last_login' => $user->last_login_at ?? $user->updated_at,
                    'is_active' => $user->is_active,
                    'preferences' => $preferences,
                ],
                'message' => 'Profile retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching profile: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile. Please try again later.'
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

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

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Try to load department if it exists
            try {
                if (method_exists($user, 'department')) {
                    $user->load('department');
                }
            } catch (\Exception $e) {
                // Ignore department loading errors
            }

            // Get avatar URL safely
            $avatarUrl = null;
            try {
                $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : null;
            } catch (\Exception $e) {
                // Ignore avatar URL errors
            }

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
                    'avatar' => $avatarUrl,
                ],
                'message' => 'Profile updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile. Please try again later.'
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

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
            Log::error('Error changing password: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password. Please try again later.'
            ], 500);
        }
    }

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

            // Validate the request
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
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

            // Ensure avatars directory exists
            $avatarsPath = storage_path('app/public/avatars');
            if (!file_exists($avatarsPath)) {
                mkdir($avatarsPath, 0755, true);
            }

            // Delete old avatar if exists
            if ($user->avatar) {
                try {
                    if (Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not delete old avatar: ' . $e->getMessage());
                    // Continue with upload even if delete fails
                }
            }

            // Generate unique filename
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Store the file
            $path = $file->storeAs('avatars', $filename, 'public');
            
            if (!$path) {
                throw new \Exception('Failed to store file');
            }

            // Update user record
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
            Log::error('Error uploading avatar: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar. Please try again later.'
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Default values
            $stats = [
                'total_materials' => 0,
                'total_videos' => 0,
                'completed_materials' => 0,
                'in_progress_materials' => 0,
                'total_time_spent' => 0,
                'total_time_spent_formatted' => '0 hours',
                'join_date' => $user->created_at,
                'last_active' => $user->updated_at,
            ];

            // Try to get real statistics if tables exist
            try {
                if (class_exists('App\Models\Material')) {
                    $stats['total_materials'] = Material::count();
                    $stats['total_videos'] = Material::where('type', 'video')->count();
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch material statistics: ' . $e->getMessage());
            }

            try {
                if (class_exists('App\Models\Progress')) {
                    $stats['completed_materials'] = Progress::where('user_id', $user->id)
                        ->where('completed', true)
                        ->count();
                    
                    $stats['total_time_spent'] = Progress::where('user_id', $user->id)
                        ->sum('time_spent_seconds') ?? 0;
                    
                    $stats['in_progress_materials'] = Progress::where('user_id', $user->id)
                        ->where('completed', false)
                        ->where('progress', '>', 0)
                        ->count();
                    
                    $stats['total_time_spent_formatted'] = $this->formatTimeSpent($stats['total_time_spent']);
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch progress statistics: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching statistics: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics. Please try again later.'
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

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

            // Get current preferences
            $currentPrefs = [];
            if ($user->preferences) {
                $currentPrefs = is_array($user->preferences) 
                    ? $user->preferences 
                    : json_decode($user->preferences, true);
            }

            // Default preferences
            $defaultPrefs = [
                'email_notifications' => true,
                'dark_mode' => false,
                'language' => 'en'
            ];

            // Merge with existing preferences
            $currentPrefs = array_merge($defaultPrefs, $currentPrefs);
            
            // Update with new values
            $newPrefs = array_merge($currentPrefs, $request->only([
                'email_notifications', 'dark_mode', 'language'
            ]));

            // Save preferences
            $user->preferences = $newPrefs;
            $user->save();

            return response()->json([
                'success' => true,
                'data' => $newPrefs,
                'message' => 'Preferences updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating preferences: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences. Please try again later.'
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $activities = [];

            // Try to get progress activity
            try {
                if (class_exists('App\Models\Progress')) {
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
                            'progress' => $item->progress ?? 0,
                            'timestamp' => $item->updated_at,
                            'message' => "Progress updated to {$item->progress}% on " . ($item->material->title ?? 'material')
                        ];
                    })->toArray();
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch activity: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $activities,
                'message' => 'Activity retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching activity: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity. Please try again later.'
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