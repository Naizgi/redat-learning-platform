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
    \Log::info('=== REGISTER REQUEST START ===');
    
    // Method 1: Get raw content and try to decode
    $rawContent = $request->getContent();
    \Log::info('Raw request content:', ['content' => $rawContent]);
    
    // Method 2: Check headers
    $contentType = $request->headers->get('Content-Type');
    \Log::info('Content-Type header:', ['content-type' => $contentType]);
    
    // Method 3: Try different ways to get data
    $data = [];
    
    // Try JSON first
    if ($request->isJson() && !empty($rawContent)) {
        $data = json_decode($rawContent, true) ?? [];
        \Log::info('Data from JSON decode:', $data);
    }
    
    // If JSON empty, try form data
    if (empty($data)) {
        $data = $request->all();
        \Log::info('Data from request->all():', $data);
    }
    
    // If still empty, try input()
    if (empty($data)) {
        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'department_id' => $request->input('department_id'),
        ];
        \Log::info('Data from individual inputs:', $data);
    }
    
    // Remove null values for logging clarity
    $filteredData = array_filter($data, function($value) {
        return $value !== null;
    });
    \Log::info('Final data to validate:', $filteredData);
    
    // If still empty, return error
    if (empty(array_filter($data))) {
        \Log::error('No data received in request');
        return response()->json([
            'success' => false,
            'message' => 'No data received. Please check your request format.',
            'debug' => [
                'content_type' => $contentType,
                'raw_content' => $rawContent,
                'is_json' => $request->isJson(),
                'headers' => $request->headers->all()
            ]
        ], 400);
    }
    
    // Now validate
    $validator = \Illuminate\Support\Facades\Validator::make($data, [
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6|confirmed',
        'department_id' => 'required|exists:departments,id',
    ]);
    
    if ($validator->fails()) {
        \Log::error('Validation failed with data:', $data);
        \Log::error('Validation errors:', $validator->errors()->toArray());
        
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
            'debug' => [
                'received_data' => $data,
                'content_type' => $contentType,
                'raw_content' => substr($rawContent, 0, 500) // First 500 chars
            ]
        ], 422);
    }
    
    $validated = $validator->validated();
    \Log::info('Validation passed:', $validated);
    
    try {
        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Let model cast hash it
            'department_id' => $validated['department_id'],
            'role' => 'student',
            'is_active' => false,
        ]);
        
        \Log::info('User created:', ['id' => $user->id, 'email' => $user->email]);
        
        // Generate OTP
        $otp = rand(100000, 999999);
        
        EmailOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
            ]
        );
        
        \Log::info('OTP created and saved:', ['otp' => $otp]);
        
        // Try to send email (but don't fail registration if email fails)
        try {
            \Log::info('Attempting to send email to: ' . $user->email);
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new \App\Mail\SendOtpMail($otp, $user->name));
            \Log::info('Email sent successfully');
        } catch (\Exception $emailException) {
            \Log::error('Email sending failed (but registration succeeded):', [
                'error' => $emailException->getMessage(),
                'otp' => $otp // Log OTP so user can verify manually
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Registration successful. ' . 
                        (isset($emailException) ? 'Check logs for OTP.' : 'OTP sent to email.'),
            'user_id' => $user->id,
            'email' => $user->email,
            'debug_otp' => $otp // Remove in production
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Registration failed:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
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




    // In AuthController
public function resendOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);
    
    $user = User::where('email', $request->email)->first();
    
    // Generate new OTP
    $otp = rand(100000, 999999);
    
    EmailOtp::updateOrCreate(
        ['user_id' => $user->id],
        [
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null, // Reset verification
        ]
    );
    
    // Send email
    try {
        \Illuminate\Support\Facades\Mail::to($user->email)
            ->send(new \App\Mail\SendOtpMail($otp, $user->name));
    } catch (\Exception $e) {
        \Log::error('Resend OTP email failed', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'OTP generated but email failed. Contact support.',
            'debug_otp' => $otp // Remove in production
        ], 500);
    }
    
    return response()->json([
        'success' => true,
        'message' => 'New OTP sent to your email.',
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