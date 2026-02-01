<?php

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
| AUTH CHECK (SESSION AWARE)
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
| FREE GIFTS (PUBLIC + SESSION AWARE)
|--------------------------------------------------------------------------
| - Guests allowed
| - Logged-in users detected automatically
| - Uses web middleware for session
*/
Route::middleware('web')->group(function () {
    Route::post('/free-gifts', [FreeGiftController::class, 'store']);
    Route::get('/free-gifts/{token}', [FreeGiftController::class, 'show']);
});

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
| PREMIUM GIFTS (AUTH REQUIRED)
|--------------------------------------------------------------------------
*/
Route::get('/premium-gifts/{id}/preview', [PremiumGiftController::class, 'preview'])
    ->middleware('auth:sanctum');
Route::post(
    '/premium-gifts/{token}/verify-secret',
    [PremiumGiftController::class, 'verifySecret']
);
/*
|--------------------------------------------------------------------------
| PREMIUM GIFTS (PUBLIC VIEW)
|--------------------------------------------------------------------------
*/
Route::get(
    '/premium-gifts/view/{token}',
    [PremiumGiftController::class, 'view']
);
Route::prefix('premium-gifts')
    ->middleware('auth:sanctum')
    ->group(function () {

    
        Route::post('/', [PremiumGiftController::class, 'store']);
        Route::get('/{id}', [PremiumGiftController::class, 'show']);
        Route::put('/{id}', [PremiumGiftController::class, 'update']);

        Route::post('/{id}/apply-coupon', [PremiumGiftController::class, 'applyCoupon']);
        Route::post('/{id}/publish', [PremiumGiftController::class, 'publish']); // future payment success


        Route::post('/{id}/publish', [PremiumGiftController::class, 'publish']);
        Route::post('/{id}/images', [PremiumGiftController::class, 'uploadImage']);

        // Route::get('/view/{token}', [PremiumGiftController::class, 'view']);
        // Route::post('/{token}/verify-secret', [PremiumGiftController::class, 'verifySecret']);
        

        Route::post('/{token}/proposal/accept', [PremiumGiftController::class, 'acceptProposal']);
    });
