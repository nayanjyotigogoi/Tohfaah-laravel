<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controllers
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Api\FreeGiftController;
use App\Http\Controllers\Api\PremiumGiftController;
use App\Http\Controllers\Api\UserDashboardController;

/*
|--------------------------------------------------------------------------
| AUTH CHECK (TOKEN BASED)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| FREE GIFTS (PUBLIC + OPTIONAL AUTH)
|--------------------------------------------------------------------------
| - Guests can create gifts
| - Logged-in users get sender_id stored
*/
Route::post('/free-gifts', [FreeGiftController::class, 'store'])
    ->middleware('optional.sanctum');

Route::get('/free-gifts/{token}', [FreeGiftController::class, 'show']);

/*
|--------------------------------------------------------------------------
| DASHBOARD (AUTH REQUIRED)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get(
    '/dashboard-data',
    [UserDashboardController::class, 'index']
);

/*
|--------------------------------------------------------------------------
| PREMIUM GIFTS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------
| Public Routes
|--------------------------------------------------
*/

// View published gift
Route::get(
    '/premium-gifts/view/{token}',
    [PremiumGiftController::class, 'viewGift']
);

// Verify secret answer
Route::post(
    '/premium-gifts/{token}/verify-secret',
    [PremiumGiftController::class, 'verifyAndUnlock']
);

/*
|--------------------------------------------------
| Authenticated Routes
|--------------------------------------------------
*/
Route::prefix('premium-gifts')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::post('/draft', [PremiumGiftController::class, 'createDraft']);

        Route::put('/draft/{id}', [PremiumGiftController::class, 'updateDraft']);

        Route::delete('/draft/{id}', [PremiumGiftController::class, 'deleteDraft']);

        Route::get('/preview/{token}', [PremiumGiftController::class, 'previewDraft']);

        Route::post('/{id}/images', [PremiumGiftController::class, 'uploadImages']);

        Route::post('/{id}/apply-coupon', [PremiumGiftController::class, 'applyCoupon']);

        Route::post('/{id}/publish', [PremiumGiftController::class, 'publishGift']);
    });

