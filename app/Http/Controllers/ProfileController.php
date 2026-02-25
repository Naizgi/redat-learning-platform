<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller; // Make sure this import is present

class ProfileController extends Controller // Extend the correct base Controller
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
            Log::info('ProfileController@getProfile called', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'authenticated' => $request->user() ? 'yes' : 'no'
            ]);

            $user = $request->user();
            
            if (!$user) {
                Log::warning('No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Basic user data without any relationships
            $profileData = [
                'id' => $user->id,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'phone' => $user->phone ?? '',
                'address' => $user->address ?? '',
                'bio' => $user->bio ?? '',
                'department_id' => $user->department_id ?? null,
                'level' => $user->level ?? '',
                'role' => $user->role ?? 'student',
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'preferences' => [
                    'emailNotifications' => true,
                    'darkMode' => false,
                    'language' => 'en'
                ]
            ];

            Log::info('Profile data retrieved successfully', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'data' => $profileData,
                'message' => 'Profile retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in getProfile: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            Log::info('ProfileController@getStatistics called', [
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Return default statistics without database queries
            $stats = [
                'total_materials' => 0,
                'total_videos' => 0,
                'completed_materials' => 0,
                'total_time_spent' => 0,
                'join_date' => $user->created_at,
                'last_active' => $user->updated_at,
            ];

            Log::info('Statistics retrieved successfully', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in getStatistics: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            Log::info('ProfileController@updateProfile called', [
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'bio' => 'nullable|string|max:1000',
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Profile updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in updateProfile: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        try {
            Log::info('ProfileController@changePassword called', [
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6',
                'confirm_password' => 'required|string|same:new_password',
            ]);

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in changePassword: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(Request $request)
    {
        try {
            Log::info('ProfileController@uploadAvatar called', [
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $path = $file->store('avatars', 'public');
                
                // Delete old avatar if exists
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                
                $user->avatar = $path;
                $user->save();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'avatar_url' => asset('storage/' . $path)
                    ],
                    'message' => 'Avatar uploaded successfully'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error in uploadAvatar: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update preferences
     */
    public function updatePreferences(Request $request)
    {
        try {
            Log::info('ProfileController@updatePreferences called', [
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'email_notifications' => 'sometimes|boolean',
                'dark_mode' => 'sometimes|boolean',
                'language' => 'sometimes|string|in:en,am',
            ]);

            // Simple preferences storage
            $preferences = [
                'emailNotifications' => $validated['email_notifications'] ?? true,
                'darkMode' => $validated['dark_mode'] ?? false,
                'language' => $validated['language'] ?? 'en'
            ];

            // You can store this in a separate table or as JSON in users table
            // For now, just return success

            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Preferences updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in updatePreferences: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activity
     */
    public function getActivity(Request $request)
    {
        try {
            Log::info('ProfileController@getActivity called', [
                'user_id' => $request->user() ? $request->user()->id : null
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Activity retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in getActivity: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }
}