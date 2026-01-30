<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Throwable;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | STEP 0: Request reached controller
        |--------------------------------------------------------------------------
        */
        Log::info('LOGIN[0]: Request received', [
            'ip' => $request->ip(),
            'has_session' => $request->hasSession(),
            'session_id' => optional($request->session())->getId(),
            'cookies' => array_keys($request->cookies->all()),
        ]);

        /*
        |--------------------------------------------------------------------------
        | STEP 1: Validate input
        |--------------------------------------------------------------------------
        */
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            Log::info('LOGIN[1]: Validation passed', [
                'email' => $credentials['email'],
            ]);
        } catch (Throwable $e) {
            Log::error('LOGIN[1]: Validation failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 2: Fetch user
        |--------------------------------------------------------------------------
        */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            Log::warning('LOGIN[2]: User not found', [
                'email' => $credentials['email'],
            ]);

            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        Log::info('LOGIN[2]: User found', [
            'user_id' => $user->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | STEP 3: Password check (explicit, for debug visibility)
        |--------------------------------------------------------------------------
        */
        $passwordMatch = Hash::check($credentials['password'], $user->password);

        Log::info('LOGIN[3]: Password check', [
            'user_id' => $user->id,
            'password_match' => $passwordMatch,
        ]);

        if (! $passwordMatch) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 4: Attempt SESSION login (CRITICAL)
        |--------------------------------------------------------------------------
        */
        $loginAttempt = Auth::attempt($credentials);

        Log::info('LOGIN[4]: Auth::attempt executed', [
            'login_attempt' => $loginAttempt,
            'guard' => config('auth.defaults.guard'),
        ]);

        if (! $loginAttempt) {
            Log::error('LOGIN[4]: Auth::attempt failed unexpectedly', [
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => ['Authentication failed.'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 5: Regenerate session (security)
        |--------------------------------------------------------------------------
        */
        try {
            $request->session()->regenerate();

            Log::info('LOGIN[5]: Session regenerated', [
                'new_session_id' => $request->session()->getId(),
            ]);
        } catch (Throwable $e) {
            Log::error('LOGIN[5]: Session regeneration failed', [
                'error' => $e->getMessage(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 6: Verify authenticated user in session
        |--------------------------------------------------------------------------
        */
        $authUser = Auth::user();

        Log::info('LOGIN[6]: Auth user after login', [
            'auth_id' => Auth::id(),
            'auth_user_exists' => (bool) $authUser,
        ]);

        if (! $authUser) {
            Log::critical('LOGIN[6]: User NOT present in session after login');

            return response()->json([
                'message' => 'Login failed (session not established).',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 7: (Optional) Create Sanctum token
        |--------------------------------------------------------------------------
        */
        try {
            $token = $authUser->createToken('spa')->plainTextToken;

            Log::info('LOGIN[7]: Sanctum token created', [
                'user_id' => $authUser->id,
            ]);
        } catch (Throwable $e) {
            Log::error('LOGIN[7]: Token creation failed', [
                'user_id' => $authUser->id,
                'error' => $e->getMessage(),
            ]);

            $token = null;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 8: Final response
        |--------------------------------------------------------------------------
        */
        Log::info('LOGIN[8]: Login successful', [
            'user_id' => $authUser->id,
            'session_id' => $request->session()->getId(),
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $authUser->id,
                'full_name' => $authUser->full_name,
                'email' => $authUser->email,
            ],
            'token' => $token, // optional (can remove later)
        ]);
    }
}
