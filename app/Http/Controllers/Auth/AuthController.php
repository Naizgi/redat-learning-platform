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
    \Log::info('=== REGISTER REQUEST START ===', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);
    
    // ===== RATE LIMITING =====
    // Prevent spam registrations
    $rateLimitKey = 'register:' . $request->ip();
    $maxAttempts = 5; // 5 registrations per hour per IP
    $decayMinutes = 60;
    
    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
        $retryAfter = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
        \Log::warning('Rate limit exceeded', ['ip' => $request->ip(), 'retry_after' => $retryAfter]);
        
        return response()->json([
            'success' => false,
            'message' => 'Too many registration attempts. Please try again in ' . ceil($retryAfter/60) . ' minutes.',
            'retry_after' => $retryAfter
        ], 429);
    }
    
    // ===== DATA EXTRACTION (KEEP YOUR EXISTING CODE) =====
    $rawContent = $request->getContent();
    $contentType = $request->headers->get('Content-Type');
    $data = [];
    
    if ($request->isJson() && !empty($rawContent)) {
        $data = json_decode($rawContent, true) ?? [];
    }
    
    if (empty($data)) {
        $data = $request->all();
    }
    
    if (empty($data)) {
        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'department_id' => $request->input('department_id'),
        ];
    }
    
    // ===== EMAIL SANITIZATION =====
    // Clean email to prevent issues
    if (isset($data['email'])) {
        $data['email'] = strtolower(trim($data['email']));
        
        // Check for disposable/temporary emails (optional)
        $disposableDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
        $emailDomain = substr(strrchr($data['email'], "@"), 1);
        
        if (in_array($emailDomain, $disposableDomains)) {
            \Log::warning('Disposable email attempt', ['email' => $data['email']]);
            // You can choose to block or allow - here we just log it
        }
    }
    
    // ===== VALIDATION (ENHANCED) =====
    $validator = \Illuminate\Support\Facades\Validator::make($data, [
        'name' => 'required|string|max:255|min:2',
        'email' => [
            'required',
            'email:rfc,dns', // Strict email validation
            'unique:users',
            'max:255'
        ],
        'password' => [
            'required',
            'min:8', // Increased from 6 to 8 for better security
            'confirmed',
            'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/' // At least 1 uppercase, 1 lowercase, 1 number
        ],
        'department_id' => 'required|exists:departments,id',
    ], [
        'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
    ]);
    
    if ($validator->fails()) {
        \Log::warning('Validation failed', [
            'errors' => $validator->errors()->toArray(),
            'ip' => $request->ip()
        ]);
        
        // Increment rate limiter on failed validation
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
        
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
            'message' => 'Validation failed. Please check your input.'
        ], 422);
    }
    
    $validated = $validator->validated();
    \Log::info('Validation passed', ['email' => $validated['email']]);
    
    // ===== TRANSACTION - ATOMIC OPERATION =====
    try {
        // Use database transaction to ensure data consistency
        \DB::beginTransaction();
        
        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'department_id' => $validated['department_id'],
            'role' => 'student',
            'is_active' => false,
            'registration_ip' => $request->ip(), // Store IP for security
            'registered_at' => now(),
        ]);
        
        \Log::info('User created', [
            'id' => $user->id, 
            'email' => $user->email,
            'department_id' => $user->department_id
        ]);
        
        // Generate OTP
        $otp = rand(100000, 999999);
        
        // Use firstOrCreate to avoid duplicates
        $emailOtp = EmailOtp::firstOrCreate(
            ['user_id' => $user->id],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0, // Track OTP attempts
            ]
        );
        
        // If OTP already existed, update it
        if ($emailOtp->wasRecentlyCreated === false) {
            $emailOtp->update([
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'verified_at' => null,
                'attempts' => 0,
            ]);
        }
        
        \Log::info('OTP created', [
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => $emailOtp->expires_at
        ]);
        
        // ===== EMAIL SENDING WITH ENHANCED ERROR HANDLING =====
        $emailSent = false;
        $emailError = null;
        
        try {
            \Log::info('Attempting to send OTP email', [
                'to' => $user->email,
                'from' => config('mail.from.address')
            ]);
            
            // Queue the email for better performance and deliverability
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->queue(new \App\Mail\SendOtpMail($otp, $user->name));
            
            $emailSent = true;
            \Log::info('OTP email queued successfully');
            
        } catch (\Exception $emailException) {
            $emailError = $emailException->getMessage();
            \Log::error('Email queueing failed', [
                'error' => $emailError,
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // Try immediate send as fallback
            try {
                \Log::info('Attempting immediate email send as fallback');
                \Illuminate\Support\Facades\Mail::to($user->email)
                    ->send(new \App\Mail\SendOtpMail($otp, $user->name));
                
                $emailSent = true;
                \Log::info('Immediate email send successful');
                
            } catch (\Exception $immediateEmailException) {
                $emailError = $immediateEmailException->getMessage();
                \Log::error('Immediate email send also failed', [
                    'error' => $emailError,
                    'otp' => $otp // Log OTP for manual verification
                ]);
            }
        }
        
        // ===== COMMIT TRANSACTION =====
        \DB::commit();
        
        // ===== SUCCESS RESPONSE =====
        $response = [
            'success' => true,
            'message' => 'Registration successful. ' . 
                        ($emailSent ? 'OTP has been sent to your email.' : 'Please check your email for OTP (may be in spam).'),
            'user_id' => $user->id,
            'email' => $user->email,
            'email_sent' => $emailSent,
            'next_step' => 'verify_otp',
        ];
        
        // Only include debug OTP in non-production environments
        if (app()->environment('local', 'staging')) {
            $response['debug_otp'] = $otp;
            $response['otp_expires'] = $emailOtp->expires_at->format('Y-m-d H:i:s');
        }
        
        // Increment rate limiter on successful registration
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
        
        return response()->json($response);
        
    } catch (\Exception $e) {
        // ===== ROLLBACK ON ERROR =====
        \DB::rollBack();
        
        \Log::error('Registration transaction failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'data' => isset($validated) ? ['email' => $validated['email']] : []
        ]);
        
        // Still increment rate limiter on error
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
        
        return response()->json([
            'success' => false,
            'message' => 'Registration failed due to a system error. Please try again.',
            'error_detail' => app()->environment('local') ? $e->getMessage() : null
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