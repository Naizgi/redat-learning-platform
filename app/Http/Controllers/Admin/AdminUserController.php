<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Department;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class AdminUserController extends Controller
{
    // List all users
    public function index(Request $request)
    {
        try {
            $users = User::query()
                ->with(['department', 'subscription'])
                ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                                      ->orWhere('email', 'like', "%{$request->search}%")
                                                      ->orWhereHas('department', function ($query) use ($request) {
                                                          $query->where('name', 'like', "%{$request->search}%");
                                                      }))
                ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
                ->when($request->role, fn($q) => $q->where('role', $request->role))
                ->when($request->status, function ($q) use ($request) {
                    if ($request->status === 'active') {
                        $q->where('is_active', true);
                    } elseif ($request->status === 'inactive') {
                        $q->where('is_active', false);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Users retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Show single user
    public function show(User $user)
    {
        try {
            $user->load(['department', 'subscription']);
            
            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'User retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Create a new user with active subscription
    public function store(Request $request)
    {
        try {
            Log::info('=== ADMIN CREATE USER START ===', [
                'admin_id' => auth()->id(),
                'ip' => $request->ip()
            ]);

            // Rate limiting for admin user creation
            $rateLimitKey = 'admin_create_user:' . $request->ip();
            $maxAttempts = 20; // 20 user creations per hour
            $decayMinutes = 60;
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($rateLimitKey);
                Log::warning('Admin user creation rate limit exceeded', [
                    'admin_id' => auth()->id(),
                    'ip' => $request->ip(),
                    'retry_after' => $retryAfter
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Too many user creation attempts. Please try again in ' . ceil($retryAfter/60) . ' minutes.',
                    'retry_after' => $retryAfter
                ], 429);
            }

            // Data extraction with support for JSON and form data
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
                    'role' => $request->input('role'),
                    'send_credentials' => $request->input('send_credentials'),
                    'subscription_duration' => $request->input('subscription_duration'),
                ];
            }

            // Email sanitization
            if (isset($data['email'])) {
                $data['email'] = strtolower(trim($data['email']));
            }

            // Enhanced validation
            $validator = Validator::make($data, [
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
                'role' => ['required', Rule::in(['student', 'instructor', 'admin'])],
                'send_credentials' => 'sometimes|boolean',
                'subscription_duration' => 'sometimes|integer|min:1|max:365', // Duration in days
                'plain_password_for_email' => 'sometimes|string|min:8', // Plain password to send in email
            ], [
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]);

            if ($validator->fails()) {
                Log::warning('Admin user creation validation failed', [
                    'admin_id' => auth()->id(),
                    'errors' => $validator->errors()->toArray()
                ]);
                
                RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
                
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed. Please check your input.'
                ], 422);
            }

            $validated = $validator->validated();
            Log::info('Admin user creation validation passed', [
                'email' => $validated['email'],
                'role' => $validated['role']
            ]);

            // Use transaction to ensure data consistency
            DB::beginTransaction();

            // Store plain password for email before hashing
            $plainPassword = $validated['plain_password_for_email'] ?? $validated['password'];

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'department_id' => $validated['department_id'],
                'role' => $validated['role'],
                'is_active' => true, // Auto-activate since admin is creating
                'email_verified_at' => now(), // Auto-verify
                'registered_by_admin' => true,
                'registered_by_admin_id' => auth()->id(),
                'registration_ip' => $request->ip(),
                'registered_at' => now(),
            ]);

            Log::info('Admin created user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'admin_id' => auth()->id()
            ]);

            // Create active subscription for the user
            $subscriptionDuration = $validated['subscription_duration'] ?? 30; // Default 30 days
            $subscriptionEndDate = now()->addDays($subscriptionDuration);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => null, // Or you can set a default plan
                'status' => 'active',
                'start_date' => now(),
                'end_date' => $subscriptionEndDate,
                'auto_renew' => false,
                'created_by_admin' => true,
                'admin_id' => auth()->id(),
                'notes' => 'Initial subscription created by admin'
            ]);

            Log::info('Subscription created for user', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'end_date' => $subscriptionEndDate->format('Y-m-d H:i:s')
            ]);

            // Send credentials email if requested
            $sendCredentials = $validated['send_credentials'] ?? true;
            $emailSent = false;
            $emailError = null;

            if ($sendCredentials) {
                try {
                    $emailData = [
                        'name' => $user->name,
                        'email' => $user->email,
                        'password' => $plainPassword, // Send plain password
                        'role' => $user->role,
                        'department' => $user->department->name ?? 'N/A',
                        'loginUrl' => config('app.frontend_url', config('app.url')) . '/login',
                        'type' => 'admin_created',
                        'siteName' => config('app.name', 'Learning Platform'),
                        'supportEmail' => config('mail.support_email', 'support@example.com'),
                        'subscription_end' => $subscriptionEndDate->format('F j, Y'),
                        'subscription_duration' => $subscriptionDuration . ' days',
                        'admin_created' => true,
                    ];

                    Mail::to($user->email)->queue(new UserCredentialsMail($emailData));
                    $emailSent = true;
                    
                    Log::info('Credentials email queued successfully', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id()
                    ]);

                } catch (\Exception $e) {
                    $emailError = $e->getMessage();
                    Log::error('Failed to send credentials email', [
                        'user_id' => $user->id,
                        'error' => $emailError
                    ]);
                }
            }

            // Commit transaction
            DB::commit();

            // Increment rate limiter
            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

            // Prepare response
            $response = [
                'success' => true,
                'message' => 'User created successfully' . 
                            ($sendCredentials && $emailSent ? ' and credentials email sent.' : 
                             ($sendCredentials ? ' but email delivery failed.' : '')),
                'data' => [
                    'user' => $user->load(['department', 'subscription']),
                    'subscription' => $subscription,
                    'email_sent' => $emailSent,
                    'email_error' => $emailError,
                    'plain_password' => app()->environment('local', 'staging') ? $plainPassword : null,
                ]
            ];

            // Only include plain password in non-production environments
            if (app()->environment('local', 'staging')) {
                $response['debug'] = [
                    'plain_password' => $plainPassword,
                    'subscription_end' => $subscriptionEndDate->format('Y-m-d H:i:s')
                ];
            }

            return response()->json($response, 201);
            
        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            
            Log::error('Admin user creation failed', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);

            // Still increment rate limiter on error
            if (isset($rateLimitKey)) {
                RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user due to a system error.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Update user
    public function update(Request $request, User $user)
    {
        try {
            Log::info('=== ADMIN UPDATE USER START ===', [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            // Rate limiting
            $rateLimitKey = 'admin_update_user:' . $request->ip();
            $maxAttempts = 30;
            $decayMinutes = 60;
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'success' => false,
                    'message' => 'Too many update attempts. Please try again later.',
                    'retry_after' => $retryAfter
                ], 429);
            }

            // Data extraction
            $rawContent = $request->getContent();
            $data = [];
            
            if ($request->isJson() && !empty($rawContent)) {
                $data = json_decode($rawContent, true) ?? [];
            }
            
            if (empty($data)) {
                $data = $request->all();
            }

            // Email sanitization
            if (isset($data['email'])) {
                $data['email'] = strtolower(trim($data['email']));
            }

            $validator = Validator::make($data, [
                'name' => 'sometimes|string|max:255|min:2',
                'email' => ['sometimes','email:rfc,dns', Rule::unique('users')->ignore($user->id), 'max:255'],
                'password' => [
                    'sometimes',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+/'
                ],
                'password_confirmation' => 'required_with:password',
                'department_id' => 'sometimes|exists:departments,id',
                'role' => ['sometimes', Rule::in(['student', 'instructor', 'admin'])],
                'is_active' => 'sometimes|boolean',
                'send_credentials' => 'sometimes|boolean',
                'plain_password_for_email' => 'sometimes|string|min:8',
                'subscription_duration' => 'sometimes|integer|min:1|max:365', // Extend subscription
                'reset_subscription' => 'sometimes|boolean', // Option to reset subscription
            ], [
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]);

            if ($validator->fails()) {
                Log::warning('Admin user update validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors()->toArray()
                ]);
                
                RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
                
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $validated = $validator->validated();

            // Use transaction
            DB::beginTransaction();

            $passwordChanged = false;
            $newPlainPassword = null;
            $emailChanged = false;
            $subscriptionUpdated = false;

            // Update user fields
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }
            
            if (isset($validated['email']) && $validated['email'] !== $user->email) {
                $user->email = $validated['email'];
                $emailChanged = true;
                // Reset email verification if email changed
                if ($user->email_verified_at) {
                    $user->email_verified_at = null;
                }
            }
            
            if (isset($validated['password'])) {
                $user->password = Hash::make($validated['password']);
                $passwordChanged = true;
                $newPlainPassword = $validated['plain_password_for_email'] ?? $validated['password'];
            }
            
            if (isset($validated['department_id'])) {
                $user->department_id = $validated['department_id'];
            }
            
            if (isset($validated['role'])) {
                $user->role = $validated['role'];
            }
            
            if (isset($validated['is_active'])) {
                $user->is_active = $validated['is_active'];
                
                // Auto-verify email if activating for the first time
                if ($validated['is_active'] && !$user->email_verified_at) {
                    $user->email_verified_at = now();
                }
                
                // If deactivating, also mark as logged out
                if (!$validated['is_active']) {
                    $user->tokens()->delete();
                }
            }

            $user->save();

            // Handle subscription updates
            if (isset($validated['subscription_duration']) || isset($validated['reset_subscription'])) {
                $subscription = $user->subscription()->first();
                
                if (!$subscription) {
                    // Create new subscription if doesn't exist
                    $subscription = Subscription::create([
                        'user_id' => $user->id,
                        'plan_id' => null,
                        'status' => 'active',
                        'start_date' => now(),
                        'end_date' => now()->addDays($validated['subscription_duration'] ?? 30),
                        'auto_renew' => false,
                        'created_by_admin' => true,
                        'admin_id' => auth()->id(),
                        'notes' => 'Subscription created/updated by admin'
                    ]);
                } else {
                    // Update existing subscription
                    if (isset($validated['reset_subscription']) && $validated['reset_subscription']) {
                        $subscription->start_date = now();
                    }
                    
                    if (isset($validated['subscription_duration'])) {
                        $subscription->end_date = now()->addDays($validated['subscription_duration']);
                    }
                    
                    $subscription->status = 'active';
                    $subscription->updated_by_admin = true;
                    $subscription->admin_id = auth()->id();
                    $subscription->notes = $subscription->notes . "\nUpdated by admin on " . now()->format('Y-m-d H:i:s');
                    $subscription->save();
                }
                
                $subscriptionUpdated = true;
                Log::info('Subscription updated', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'end_date' => $subscription->end_date->format('Y-m-d H:i:s')
                ]);
            }

            // Send credentials email if requested or if password/email was changed
            $sendCredentials = $validated['send_credentials'] ?? false;
            $emailSent = false;
            $emailError = null;

            if ($sendCredentials || $passwordChanged || $emailChanged) {
                try {
                    $passwordToSend = $newPlainPassword ?? 'Use your existing password';
                    $emailType = $passwordChanged ? 'password_reset' : ($emailChanged ? 'email_update' : 'account_update');
                    
                    $emailData = [
                        'name' => $user->name,
                        'email' => $user->email,
                        'password' => $passwordToSend,
                        'role' => $user->role,
                        'department' => $user->department->name ?? 'N/A',
                        'loginUrl' => config('app.frontend_url', config('app.url')) . '/login',
                        'type' => $emailType,
                        'siteName' => config('app.name', 'Learning Platform'),
                        'supportEmail' => config('mail.support_email', 'support@example.com'),
                        'admin_updated' => true,
                        'changes' => [
                            'password_changed' => $passwordChanged,
                            'email_changed' => $emailChanged,
                            'subscription_updated' => $subscriptionUpdated
                        ]
                    ];

                    // Add subscription info if updated
                    if ($subscriptionUpdated && $subscription) {
                        $emailData['subscription_end'] = $subscription->end_date->format('F j, Y');
                        $emailData['subscription_status'] = $subscription->status;
                    }

                    Mail::to($user->email)->queue(new UserCredentialsMail($emailData));
                    $emailSent = true;
                    
                    Log::info('Update credentials email sent', [
                        'user_id' => $user->id,
                        'type' => $emailType
                    ]);

                } catch (\Exception $e) {
                    $emailError = $e->getMessage();
                    Log::error('Failed to send update email', [
                        'user_id' => $user->id,
                        'error' => $emailError
                    ]);
                }
            }

            // Commit transaction
            DB::commit();

            // Increment rate limiter
            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

            // Prepare response
            $response = [
                'success' => true,
                'message' => 'User updated successfully' . 
                            ($emailSent ? ' and notification email sent.' : 
                             (($sendCredentials || $passwordChanged || $emailChanged) ? ' but email delivery failed.' : '')),
                'data' => [
                    'user' => $user->load(['department', 'subscription']),
                    'email_sent' => $emailSent,
                    'email_error' => $emailError,
                    'changes' => [
                        'password_changed' => $passwordChanged,
                        'email_changed' => $emailChanged,
                        'subscription_updated' => $subscriptionUpdated
                    ],
                    'plain_password' => ($passwordChanged && app()->environment('local', 'staging')) ? $newPlainPassword : null,
                ]
            ];

            return response()->json($response);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Admin user update failed', [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($rateLimitKey)) {
                RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Delete user
    public function destroy(User $user)
    {
        try {
            // Prevent deleting yourself
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account.'
                ], 403);
            }
            
            // Log before deletion
            Log::info('Admin deleting user', [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            
            // Delete associated subscriptions
            $user->subscription()->delete();
            
            // Delete user
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'User and associated data deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Resend credentials to user
     */
    public function resendCredentials(Request $request, User $user)
    {
        try {
            Log::info('=== ADMIN RESEND CREDENTIALS START ===', [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            $validator = Validator::make($request->all(), [
                'new_password' => 'sometimes|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+/',
                'generate_password' => 'sometimes|boolean',
                'plain_password_for_email' => 'sometimes|string|min:8', // Plain password to send in email
            ], [
                'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $passwordChanged = false;
            $newPlainPassword = null;

            // Generate or use provided password
            if ($request->boolean('generate_password')) {
                $newPlainPassword = $this->generatePassword();
                $user->password = Hash::make($newPlainPassword);
                $passwordChanged = true;
            } elseif ($request->has('new_password')) {
                $newPlainPassword = $request->input('plain_password_for_email') ?? $request->new_password;
                $user->password = Hash::make($request->new_password);
                $passwordChanged = true;
            }

            if ($passwordChanged) {
                $user->save();
                Log::info('Password changed during credentials resend', [
                    'user_id' => $user->id,
                    'admin_id' => auth()->id()
                ]);
            }

            // Send email with either new or existing credentials
            $passwordToSend = $newPlainPassword ?? 'Use your existing password';
            
            try {
                $emailData = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $passwordToSend,
                    'role' => $user->role,
                    'department' => $user->department->name ?? 'N/A',
                    'loginUrl' => config('app.frontend_url', config('app.url')) . '/login',
                    'type' => $passwordChanged ? 'password_reset' : 'credentials_reminder',
                    'siteName' => config('app.name', 'Learning Platform'),
                    'supportEmail' => config('mail.support_email', 'support@example.com'),
                    'admin_sent' => true,
                ];

                Mail::to($user->email)->queue(new UserCredentialsMail($emailData));
                
                Log::info('Credentials email resent', [
                    'user_id' => $user->id,
                    'password_changed' => $passwordChanged,
                    'admin_id' => auth()->id()
                ]);

                $response = [
                    'success' => true,
                    'data' => [
                        'user_id' => $user->id,
                        'email_sent' => true,
                        'password_changed' => $passwordChanged,
                        'new_password' => ($passwordChanged && app()->environment('local', 'staging')) ? $newPlainPassword : null
                    ],
                    'message' => $passwordChanged 
                        ? 'New credentials sent to user email.'
                        : 'Credentials email sent with existing password.'
                ];

                // Include plain password for debugging in non-production
                if ($passwordChanged && app()->environment('local', 'staging')) {
                    $response['debug'] = ['plain_password' => $newPlainPassword];
                }

                return response()->json($response);

            } catch (\Exception $e) {
                Log::error('Failed to send credentials email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Credentials updated but failed to send email. Please contact the user directly.',
                    'new_password' => ($passwordChanged && app()->environment('local', 'staging')) ? $newPlainPassword : null
                ], 200); // Return 200 since password was changed, just email failed
            }

        } catch (\Exception $e) {
            Log::error('Failed to resend credentials', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend credentials: ' . $e->getMessage(),
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate a secure random password
     */
    private function generatePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Bulk actions for users
     */
    public function bulkActions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'action' => 'required|in:activate,deactivate,delete,resend_credentials',
                'send_emails' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $userIds = $request->user_ids;
            $action = $request->action;
            $sendEmails = $request->boolean('send_emails', false);
            $results = [
                'success' => 0,
                'failed' => 0,
                'details' => []
            ];

            DB::beginTransaction();

            foreach ($userIds as $userId) {
                try {
                    $user = User::find($userId);
                    
                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = ['user_id' => $userId, 'status' => 'failed', 'reason' => 'User not found'];
                        continue;
                    }

                    // Prevent self-modification for certain actions
                    if ($user->id === auth()->id() && in_array($action, ['deactivate', 'delete'])) {
                        $results['failed']++;
                        $results['details'][] = ['user_id' => $userId, 'status' => 'failed', 'reason' => 'Cannot perform this action on yourself'];
                        continue;
                    }

                    switch ($action) {
                        case 'activate':
                            $user->is_active = true;
                            if (!$user->email_verified_at) {
                                $user->email_verified_at = now();
                            }
                            $user->save();
                            break;

                        case 'deactivate':
                            $user->is_active = false;
                            $user->tokens()->delete(); // Log them out
                            $user->save();
                            break;

                        case 'delete':
                            // Delete associated data
                            $user->subscription()->delete();
                            $user->delete();
                            break;

                        case 'resend_credentials':
                            // This would require more complex logic for bulk resend
                            // For now, just mark as not implemented
                            $results['details'][] = ['user_id' => $userId, 'status' => 'skipped', 'reason' => 'Bulk resend not implemented'];
                            continue 2;
                    }

                    $results['success']++;
                    $results['details'][] = ['user_id' => $userId, 'status' => 'success'];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'user_id' => $userId, 
                        'status' => 'failed', 
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            Log::info('Bulk action completed', [
                'admin_id' => auth()->id(),
                'action' => $action,
                'total_users' => count($userIds),
                'success' => $results['success'],
                'failed' => $results['failed']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk action completed. Success: {$results['success']}, Failed: {$results['failed']}",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk action failed', [
                'admin_id' => auth()->id(),
                'action' => $request->action ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk action failed',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }
}