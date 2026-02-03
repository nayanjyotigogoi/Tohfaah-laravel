<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        | STEP 3: Password check
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
        | STEP 4: Create Sanctum token (TOKEN-ONLY AUTH)
        |--------------------------------------------------------------------------
        */
        try {
            $token = $user->createToken('spa')->plainTextToken;

            Log::info('LOGIN[4]: Sanctum token created', [
                'user_id' => $user->id,
            ]);
        } catch (Throwable $e) {
            Log::error('LOGIN[4]: Token creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Login failed. Please try again.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 5: Final response
        |--------------------------------------------------------------------------
        */
        Log::info('LOGIN[5]: Login successful (token-based)', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }
}
