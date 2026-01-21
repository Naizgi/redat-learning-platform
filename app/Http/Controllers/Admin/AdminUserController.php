<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;

class AdminUserController extends Controller
{
    // List all users
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                                  ->orWhere('email', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($users);
    }

    // Show single user
    public function show(User $user)
    {
        return response()->json($user);
    }

    // Create a new user
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => ['required', Rule::in(['student', 'instructor', 'admin'])],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'email_verified_at' => now(), // Auto-verify since admin is creating
            'is_active' => true, // Auto-activate
        ]);

        // Send credentials email
        $this->sendCredentialsEmail($user, $request->password);

        return response()->json([
            'message' => 'User created successfully. Credentials have been sent to the user\'s email.',
            'user'    => $user
        ], 201);
    }

    // Update user
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => ['sometimes','email', Rule::unique('users')->ignore($user->id)],
            'password'  => 'sometimes|string|min:6',
            'role'      => ['sometimes', Rule::in(['student', 'instructor', 'admin'])],
            'is_active' => 'sometimes|boolean',
            'send_credentials' => 'sometimes|boolean', // Add flag to send credentials
        ]);

        $passwordChanged = false;
        $newPassword = null;

        // Existing updates...
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
            $passwordChanged = true;
            $newPassword = $request->password;
        }
        if ($request->has('role')) {
            $user->role = $request->role;
        }
        
        // Add is_active update
        if ($request->has('is_active')) {
            $user->is_active = $request->is_active;
            
            // Auto-verify email if activating for the first time
            if ($request->is_active && !$user->email_verified_at) {
                $user->email_verified_at = now();
            }
        }

        $user->save();

        // Send credentials email if requested or if password was changed
        if ($request->boolean('send_credentials') || $passwordChanged) {
            $this->sendCredentialsEmail(
                $user, 
                $newPassword ?? 'Use your existing password', // Show existing password if not changed
                $passwordChanged ? 'updated' : 'existing'
            );
            
            $message = 'User updated successfully' . 
                      ($passwordChanged || $request->boolean('send_credentials') 
                          ? '. Credentials have been sent to the user\'s email.' 
                          : '');
        } else {
            $message = 'User updated successfully';
        }

        return response()->json([
            'message' => $message,
            'user'    => $user
        ]);
    }

    // Delete user
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
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
                'loginUrl' => config('app.frontend_url') . '/login',
                'type' => $type,
                'siteName' => config('app.name', 'Learning Platform'),
                'supportEmail' => config('mail.support_email', 'support@example.com'),
            ];

            // Send email
            Mail::to($user->email)->send(new \App\Mail\UserCredentialsMail($emailData));

            \Log::info('Credentials email sent successfully', ['user_id' => $user->id]);

        } catch (\Exception $e) {
            \Log::error('Failed to send credentials email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw error, just log it - admin can resend if needed
        }
    }

    /**
     * Resend credentials to user
     */
    public function resendCredentials(Request $request, User $user)
    {
        $request->validate([
            'new_password' => 'sometimes|string|min:6',
            'generate_password' => 'sometimes|boolean',
        ]);

        try {
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
                'message' => 'Credentials have been resent to ' . $user->email,
                'new_password' => $request->boolean('generate_password') ? $newPassword : null,
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