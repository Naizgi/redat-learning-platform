<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    // Send password reset OTP via the shared UserCredentialsMail (type = password_reset_otp)
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = strtolower(trim($request->email));

        // Generate 6-digit numeric OTP
        $token = sprintf('%06d', mt_rand(0, 999999));

        // Store or update token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $token, 'created_at' => now()]
        );

        $user = User::where('email', $email)->first();

        // Send via the shared mailable + shared blade
        // Blade branches on `type` and renders the OTP variant.
        try {
            Mail::to($email)->send(new UserCredentialsMail([
                'type'               => 'password_reset_otp',
                'name'               => $user->name ?? 'there',
                'email'              => $email,
                'otp'                => $token,
                'expires_in_minutes' => 15,
                'reset_url'          => rtrim(config('app.url'), '/') . '/reset-password',
                'support_email'      => 'support@redatlearninghub.com',
                'site_name'          => 'Redat Learning Hub',
            ]));
        } catch (\Throwable $e) {
            \Log::error('Password reset OTP email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not send reset code. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset OTP sent to email.',
        ]);
    }

    // Reset the password
    public function reset(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'token'    => 'required|string',
            'password' => 'required|string|confirmed|min:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid token.'], 400);
        }

        // Check expiration (valid for 15 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            return response()->json(['message' => 'Token expired.'], 400);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful.']);
    }
}