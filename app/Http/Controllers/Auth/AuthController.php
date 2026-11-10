<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailOtp;
use App\Mail\SendOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    /* ================= REGISTER ================= */
    public function register(Request $request)
    {
        \Log::info('=== REGISTER REQUEST START ===', [
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        /* ---------- RATE LIMITING ---------- */
        $rateLimitKey = 'register:' . $request->ip();
        $maxAttempts  = 5;
        $decayMinutes = 60;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            \Log::warning('Rate limit exceeded', ['ip' => $request->ip(), 'retry_after' => $retryAfter]);

            return response()->json([
                'success'     => false,
                'message'     => 'Too many registration attempts. Please try again in ' . ceil($retryAfter / 60) . ' minutes.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        /* ---------- DATA EXTRACTION ---------- */
        $rawContent = $request->getContent();
        $data       = [];

        if ($request->isJson() && !empty($rawContent)) {
            $data = json_decode($rawContent, true) ?? [];
        }
        if (empty($data)) {
            $data = $request->all();
        }
        if (empty($data)) {
            $data = [
                'name'                  => $request->input('name'),
                'email'                 => $request->input('email'),
                'password'              => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'department_id'         => $request->input('department_id'),
            ];
        }

        /* ---------- EMAIL SANITIZATION ---------- */
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));

            $disposableDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
            $emailDomain = substr(strrchr($data['email'], "@"), 1);

            if (in_array($emailDomain, $disposableDomains)) {
                \Log::warning('Disposable email attempt', ['email' => $data['email']]);
            }
        }

        /* ---------- VALIDATION ---------- */
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255|min:2',
            'email' => [
                'required',
                'email:rfc,dns',
                'unique:users',
                'max:255',
            ],
            'password' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'department_id' => 'required|exists:departments,id',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);

        if ($validator->fails()) {
            \Log::warning('Validation failed', [
                'errors' => $validator->errors()->toArray(),
                'ip'     => $request->ip(),
            ]);

            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed. Please check your input.',
            ], 422);
        }

        $validated = $validator->validated();
        \Log::info('Validation passed', ['email' => $validated['email']]);

        /* ---------- TRANSACTION ---------- */
        try {
            DB::beginTransaction();

            $user = User::create([
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'password'      => $validated['password'],
                'department_id' => $validated['department_id'],
                'role'          => 'student',
                'is_active'     => false,
            ]);

            \Log::info('User created', [
                'id'            => $user->id,
                'email'         => $user->email,
                'department_id' => $user->department_id,
            ]);

            /* ---------- OTP ---------- */
            $otp = rand(100000, 999999);

            $emailOtp = EmailOtp::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'otp'        => $otp,
                    'expires_at' => now()->addMinutes(10),
                ]
            );

            if ($emailOtp->wasRecentlyCreated === false) {
                $emailOtp->update([
                    'otp'         => $otp,
                    'expires_at'  => now()->addMinutes(10),
                    'verified_at' => null,
                ]);
            }

            \Log::info('OTP created', [
                'user_id'    => $user->id,
                'otp'        => $otp,
                'expires_at' => $emailOtp->expires_at,
            ]);

            /* ---------- EMAIL ---------- */
            $emailSent  = false;
            $emailError = null;

            try {
                \Log::info('Attempting to send OTP email', [
                    'to'   => $user->email,
                    'from' => config('mail.from.address'),
                ]);

                Mail::to($user->email)->queue(new SendOtpMail($otp, $user->name));
                $emailSent = true;
                \Log::info('OTP email queued successfully');

            } catch (\Exception $emailException) {
                $emailError = $emailException->getMessage();
                \Log::error('Email queueing failed', [
                    'error'   => $emailError,
                    'user_id' => $user->id,
                    'email'   => $user->email,
                ]);

                try {
                    \Log::info('Attempting immediate email send as fallback');
                    Mail::to($user->email)->send(new SendOtpMail($otp, $user->name));
                    $emailSent = true;
                    \Log::info('Immediate email send successful');
                } catch (\Exception $immediateEmailException) {
                    $emailError = $immediateEmailException->getMessage();
                    \Log::error('Immediate email send also failed', [
                        'error' => $emailError,
                        'otp'   => $otp,
                    ]);
                }
            }

            DB::commit();

            $response = [
                'success'    => true,
                'message'    => 'Registration successful. ' .
                                ($emailSent
                                    ? 'OTP has been sent to your email.'
                                    : 'Please check your email for OTP (may be in spam).'),
                'user_id'    => $user->id,
                'email'      => $user->email,
                'email_sent' => $emailSent,
                'next_step'  => 'verify_otp',
            ];

            if (app()->environment('local', 'staging')) {
                $response['debug_otp']   = $otp;
                $response['otp_expires'] = $emailOtp->expires_at->format('Y-m-d H:i:s');
            }

            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Registration transaction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data'  => isset($validated) ? ['email' => $validated['email']] : [],
            ]);

            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

            return response()->json([
                'success'      => false,
                'message'      => 'Registration failed due to a system error. Please try again.',
                'error_detail' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /* ================= VERIFY OTP ================= */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|numeric|digits:6',
        ]);

        $email = strtolower(trim($validated['email']));
        $otp   = $validated['otp'];

        \Log::info('Verifying OTP', ['email' => $email, 'otp' => $otp]);

        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                \Log::warning('User not found for OTP verification', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'errors'  => ['email' => ['The provided email does not exist.']],
                ], 404);
            }

            \Log::info('User found', ['user_id' => $user->id]);

            $emailOtp = EmailOtp::where('user_id', $user->id)
                ->where('otp', $otp)
                ->whereNull('verified_at')
                ->first();

            if (!$emailOtp) {
                \Log::warning('OTP not found or already verified', [
                    'user_id' => $user->id,
                    'otp'     => $otp,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                    'errors'  => ['otp' => ['Invalid verification code.']],
                ], 422);
            }

            if (Carbon::now()->gt($emailOtp->expires_at)) {
                \Log::warning('OTP expired', [
                    'user_id'      => $user->id,
                    'expires_at'   => $emailOtp->expires_at,
                    'current_time' => now(),
                ]);

                $emailOtp->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'OTP expired',
                    'errors'  => ['otp' => ['Verification code has expired. Please request a new one.']],
                ], 422);
            }

            \Log::info('OTP verified successfully', [
                'user_id' => $user->id,
                'otp_id'  => $emailOtp->id,
            ]);

            $emailOtp->update([
                'verified_at' => now(),
            ]);

            $user->update([
                'email_verified_at' => now(),
            ]);

            \Log::info('Email verified for user', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully. Wait for admin activation.',
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

        } catch (\Exception $e) {
            \Log::error('OTP verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed due to a system error.',
                'errors'  => ['system' => ['An unexpected error occurred. Please try again.']],
            ], 500);
        }
    }

    /* ================= RESEND OTP ================= */
    public function resendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = strtolower(trim($validated['email']));

        \Log::info('Resending OTP', ['email' => $email]);

        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                \Log::warning('User not found for OTP resend', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'errors'  => ['email' => ['The provided email does not exist.']],
                ], 404);
            }

            \Log::info('User found for OTP resend', ['user_id' => $user->id]);

            $otp = rand(100000, 999999);

            EmailOtp::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'otp'         => $otp,
                    'expires_at'  => now()->addMinutes(10),
                    'verified_at' => null,
                ]
            );

            \Log::info('New OTP generated', [
                'user_id'    => $user->id,
                'otp'        => $otp,
                'expires_at' => now()->addMinutes(10),
            ]);

            try {
                Mail::to($user->email)->send(new SendOtpMail($otp, $user->name));
                \Log::info('Resend OTP email sent', ['user_id' => $user->id]);

            } catch (\Exception $e) {
                \Log::error('Resend OTP email failed', [
                    'error'   => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success'   => true,
                    'message'   => 'New OTP generated but email delivery failed. Please contact support.',
                    'debug_otp' => app()->environment('local', 'staging') ? $otp : null,
                    'warning'   => 'Email delivery failed',
                ]);
            }

            return response()->json([
                'success'   => true,
                'message'   => 'New OTP sent to your email.',
                'debug_otp' => app()->environment('local', 'staging') ? $otp : null,
            ]);

        } catch (\Exception $e) {
            \Log::error('Resend OTP failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
                'errors'  => ['system' => ['An unexpected error occurred.']],
            ], 500);
        }
    }

    /* ================= LOGIN ================= */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::whereEmail($request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Email not verified'], 403);
        }

        // Only students require admin activation.
        // Admins & instructors are implicitly active once email is verified.
        if ($user->role === 'student' && !$user->is_active) {
            return response()->json(['message' => 'Account not activated by admin'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // Non-fatal last-login update
        try {
            $user->update(['last_login_at' => now()]);
        } catch (\Throwable $e) {
            // ignore if column missing
        }

        return response()->json([
            'success'     => true,
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $user,
            'role'        => $user->role,
            'redirect_to' => $this->redirectForRole($user->role),
        ]);
    }

    /**
     * Where the frontend should navigate after a successful login.
     */
    private function redirectForRole(string $role): string
    {
        return match ($role) {
            'admin', 'instructor' => '/admin/dashboard',
            default               => '/learnthru',
        };
    }

    /* ================= LOGOUT ================= */
    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ================= GET AUTHENTICATED USER ================= */
    public function user(Request $request)
    {
        try {
            if (!$request->user()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $user = User::with('department')->find($request->user()->id);

            return response()->json([
                'success' => true,
                'user'    => $user,
                'role'    => $user->role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ================= REFRESH TOKEN ================= */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success'     => true,
                'token'       => $token,
                'user'        => $user,
                'role'        => $user->role,
                'redirect_to' => $this->redirectForRole($user->role),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error'   => $e->getMessage(),
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
                    'success'       => false,
                    'message'       => 'Not authenticated',
                    'authenticated' => false,
                ]);
            }

            return response()->json([
                'success'       => true,
                'authenticated' => true,
                'user'          => $user,
                'role'          => $user->role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success'       => false,
                'message'       => 'Auth check failed',
                'authenticated' => false,
                'error'         => $e->getMessage(),
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
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $validated = $request->validate([
                'name'          => 'sometimes|string|max:255',
                'email'         => 'sometimes|email|unique:users,email,' . $user->id,
                'phone'         => 'sometimes|nullable|string|max:20',
                'department_id' => 'sometimes|exists:departments,id',
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user'    => $user->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error'   => $e->getMessage(),
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
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required',
                'new_password'     => 'required|min:6|confirmed',
            ]);

            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }

            $user->update([
                'password' => Hash::make($validated['new_password']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}