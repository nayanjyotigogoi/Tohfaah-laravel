<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Check if user exists by google_id
            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                // Check if user exists by email
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    // Update existing user with google_id
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar_url' => $user->avatar_url ?? $googleUser->avatar,
                    ]);
                }
                else {
                    // Create new user
                    $user = User::create([
                        'id' => Str::uuid(),
                        'name' => $googleUser->name, // Note: User model uses 'full_name', need to check mapping
                        'full_name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'avatar_url' => $googleUser->avatar,
                        'password' => bcrypt(Str::random(16)), // Random password
                        'terms_accepted_at' => now(),
                    ]);

                    // Send welcome email
                    try {
                        Mail::to($user->email)->send(new WelcomeUserMail($user));
                    }
                    catch (\Throwable $e) {
                        Log::warning('GOOGLE_AUTH: welcome email failed', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect to frontend
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/auth/google/callback?token=' . $token);

        }
        catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 400);
        }
    }
}
