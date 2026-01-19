<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailOtp;
use App\Mail\SendOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    /* ================= REGISTER ================= */
    public function register(Request $request)
{
    \Log::info('Register attempt started', $request->all());
    
    $data = $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6|confirmed',
        'department_id' => 'required|exists:departments,id',
    ]);
    
    \Log::info('Validation passed', $data);

    try {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'department_id' => $data['department_id'],
            'role' => 'student',
            'is_active' => false,
        ]);
        
        \Log::info('User created successfully', ['user_id' => $user->id, 'email' => $user->email]);
        
        $otp = rand(100000, 999999);
        
        \Log::info('OTP generated', ['otp' => $otp]);

        EmailOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
            ]
        );
        
        \Log::info('OTP saved');

        // For testing, don't send actual email
        \Log::info('Would send OTP email to: ' . $user->email, ['otp' => $otp]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. OTP sent to email.',
            'debug_otp' => $otp, // Add this for debugging
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Registration failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Registration failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /* ================= VERIFY OTP ================= */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        $user = User::whereEmail($request->email)->firstOrFail();

        $otp = EmailOtp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->whereNull('verified_at')
            ->first();

        if (!$otp || Carbon::now()->gt($otp->expires_at)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP',
            ]);
        }

        $otp->update(['verified_at' => now()]);
        $user->update(['email_verified_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. Wait for admin activation.',
        ]);
    }

    /* ================= LOGIN ================= */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::whereEmail($request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Email not verified'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account not activated by admin'
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
        ]);
    }

    /* ================= LOGOUT ================= */
    public function logout(Request $request)
    {
        try {
            // Revoke all tokens for the authenticated user
            if ($request->user()) {
                $request->user()->tokens()->delete();
            }
            
            // Alternative: Revoke only the current token
            // $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ================= GET AUTHENTICATED USER ================= */
    public function user(Request $request)
    {
        try {
            // Check if user is authenticated
            if (!$request->user()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get the authenticated user with relations if needed
            $user = User::with('department')
                ->find($request->user()->id);

            return response()->json([
                'success' => true,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ================= REFRESH/GET NEW TOKEN ================= */
    public function refreshToken(Request $request)
    {
        try {
            // Get the current user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Delete all existing tokens (optional - for security)
            // $user->tokens()->delete();

            // Create a new token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ================= CHECK AUTH STATUS ================= */
    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated',
                    'authenticated' => false
                ]);
            }

            return response()->json([
                'success' => true,
                'authenticated' => true,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auth check failed',
                'authenticated' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ================= UPDATE USER PROFILE ================= */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'phone' => 'sometimes|nullable|string|max:20',
                'department_id' => 'sometimes|exists:departments,id',
                // Add other fields as needed
            ]);

            // Update user
            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user->fresh() // Get fresh instance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ================= CHANGE PASSWORD ================= */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:6|confirmed',
            ]);

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}