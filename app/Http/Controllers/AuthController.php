<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\WelcomeUserMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
    {
        Log::info('REGISTER: request received', [
            'payload' => $request->except(['password', 'password_confirmation']),
        ]);

        try {
            $validated = $request->validate(
                [
                    'name' => 'required|string|max:100',
                    'email' => 'required|email|unique:users,email',
                    'password' => 'required|min:8|confirmed',
                    'terms_accepted' => 'accepted',
                ],
                [
                    'name.required' => 'Name is required.',
                    'email.required' => 'Email is required.',
                    'email.email' => 'Please enter a valid email address.',
                    'email.unique' => 'This email is already registered.',
                    'password.required' => 'Password is required.',
                    'password.min' => 'Password must be at least 8 characters.',
                    'password.confirmed' => 'Passwords do not match.',
                    'terms_accepted.accepted' => 'You must accept the Terms & Privacy Policy.',
                ]
            );

            $user = User::create([
                'full_name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'terms_accepted_at' => now(),
            ]);

            Log::info('REGISTER: user created', ['user_id' => $user->id]);

            /**
             * SEND WELCOME EMAIL (NON-BLOCKING)
             */
            try {
                Mail::to($user->email)->send(new WelcomeUserMail($user));
            } catch (Throwable $mailError) {
                Log::warning('REGISTER: welcome email failed', [
                    'user_id' => $user->id,
                    'error' => $mailError->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully. Please log in.',
                'redirect' => '/login',
            ], 201);

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('REGISTER: fatal error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }
}
