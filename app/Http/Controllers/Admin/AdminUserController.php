<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;

class AdminUserController extends Controller
{
    // List all users
    public function index(Request $request)
    {
        try {
            $users = User::query()
                ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                                      ->orWhere('email', 'like', "%{$request->search}%"))
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Users retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Show single user
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'User retrieved successfully'
        ]);
    }

    // Create a new user
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'password_confirmation' => 'sometimes|string|min:6|same:password',
                'role' => ['required', Rule::in(['student', 'instructor', 'admin'])],
                'send_credentials' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'email_verified_at' => now(), // Auto-verify since admin is creating
                'is_active' => true, // Auto-activate
            ]);

            // Send credentials email if requested
            if ($request->boolean('send_credentials', true)) {
                $this->sendCredentialsEmail($user, $request->password, 'new');
            }

            return response()->json([
                'success' => true,
                'message' => $request->boolean('send_credentials', true) 
                    ? 'User created successfully. Credentials email sent.'
                    : 'User created successfully.',
                'data' => $user
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update user
    public function update(Request $request, User $user)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => ['sometimes','email', Rule::unique('users')->ignore($user->id)],
                'password' => 'sometimes|string|min:6',
                'password_confirmation' => 'sometimes|string|min:6|same:password',
                'role' => ['sometimes', Rule::in(['student', 'instructor', 'admin'])],
                'is_active' => 'sometimes|boolean',
                'send_credentials' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $passwordChanged = false;
            $newPassword = null;

            if ($request->has('name')) {
                $user->name = $request->name;
            }
            
            if ($request->has('email') && $request->email !== $user->email) {
                $user->email = $request->email;
                // Reset email verification if email changed
                if ($user->email_verified_at) {
                    $user->email_verified_at = null;
                }
            }
            
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
                $passwordChanged = true;
                $newPassword = $request->password;
            }
            
            if ($request->has('role')) {
                $user->role = $request->role;
            }
            
            if ($request->has('is_active')) {
                $user->is_active = $request->boolean('is_active');
                
                // Auto-verify email if activating for the first time
                if ($request->boolean('is_active') && !$user->email_verified_at) {
                    $user->email_verified_at = now();
                }
            }

            $user->save();

            // Send credentials email if requested or if password was changed
            if ($request->boolean('send_credentials') || $passwordChanged) {
                $this->sendCredentialsEmail(
                    $user, 
                    $passwordChanged ? $newPassword : 'Use your existing password',
                    $passwordChanged ? 'reset' : 'update'
                );
                
                $message = 'User updated successfully' . 
                          ($passwordChanged || $request->boolean('send_credentials') 
                              ? '. Credentials email sent.' 
                              : '');
            } else {
                $message = 'User updated successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $user
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
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
            
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send credentials email to user
     */
    private function sendCredentialsEmail(User $user, string $password, string $type = 'new')
    {
        try {
            \Log::info('Sending credentials email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'type' => $type
            ]);

            // Prepare email data
            $emailData = [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
                'role' => $user->role,
                'loginUrl' => config('app.frontend_url', config('app.url')) . '/login',
                'type' => $type,
                'siteName' => config('app.name', 'Learning Platform'),
                'supportEmail' => config('mail.support_email', 'support@example.com'),
            ];

            // Send email - if mail fails, just log it
            Mail::to($user->email)->send(new UserCredentialsMail($emailData));

            \Log::info('Credentials email sent successfully', ['user_id' => $user->id]);

        } catch (\Exception $e) {
            \Log::error('Failed to send credentials email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw error - just log it
        }
    }

    /**
     * Resend credentials to user
     */
    public function resendCredentials(Request $request, User $user)
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_password' => 'sometimes|string|min:6',
                'generate_password' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }

            $passwordChanged = false;
            $newPassword = null;

            // Generate or use provided password
            if ($request->boolean('generate_password')) {
                $newPassword = $this->generatePassword();
                $user->password = Hash::make($newPassword);
                $passwordChanged = true;
            } elseif ($request->has('new_password')) {
                $newPassword = $request->new_password;
                $user->password = Hash::make($newPassword);
                $passwordChanged = true;
            }

            if ($passwordChanged) {
                $user->save();
            }

            // Send email with either new or existing credentials
            $passwordToSend = $newPassword ?? 'Use your existing password';
            $this->sendCredentialsEmail(
                $user, 
                $passwordToSend,
                $passwordChanged ? 'reset' : 'reminder'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'email_sent' => true,
                    'password_changed' => $passwordChanged,
                    'new_password' => $passwordChanged ? $newPassword : null
                ],
                'message' => $passwordChanged 
                    ? 'New credentials sent to user email.'
                    : 'Credentials email sent with existing password.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to resend credentials', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend credentials: ' . $e->getMessage()
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
}