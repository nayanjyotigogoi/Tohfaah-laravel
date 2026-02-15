<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    /**
     * ----------------------------------
     * REQUEST RESET TOKEN (OTP)
     * ----------------------------------
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;

        // Check if user signed up with Google
        $user = \App\Models\User::where('email', $email)->first();
        if ($user && $user->google_id) {
            return response()->json(['message' => 'This account uses Google Sign-In. Please sign in with Google.'], 400);
        }

        $code = rand(100000, 999999);


        // Store code (update existing or create new)
        DB::table('password_reset_codes')->updateOrInsert(
        ['email' => $email],
        [
            'code' => $code,
            'created_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]
        );

        // Send Email
        try {
            Mail::to($email)->send(new \App\Mail\ResetPasswordOtpMail($code));
        }
        catch (\Exception $e) {
            Log::error('Password Reset Email Failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to send reset code. Please try again later.'], 500);
        }

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    /**
     * ----------------------------------
     * RESET PASSWORD WITH OTP
     * ----------------------------------
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ]);

        // Verify Code
        $record = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$record || now()->gt($record->expires_at)) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        // Update Password
        $user = \App\Models\User::where('email', $request->email)->first();
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        $user->setRememberToken(Str::random(60));

        // Delete Code
        DB::table('password_reset_codes')->where('email', $request->email)->delete();

        event(new PasswordReset($user));

        return response()->json(['message' => 'Password reset successful.']);
    }
}
