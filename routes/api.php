<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/* |-------------------------------------------------------------------------- | Controllers |-------------------------------------------------------------------------- */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Api\FreeGiftController;
use App\Http\Controllers\Api\PremiumGiftController;
use App\Http\Controllers\Api\MemoryMapController;
use App\Http\Controllers\Api\UserDashboardController;

/* |-------------------------------------------------------------------------- | AUTH CHECK (TOKEN BASED) |-------------------------------------------------------------------------- */
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/* |-------------------------------------------------------------------------- | AUTH ROUTES |-------------------------------------------------------------------------- */
Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class , 'register']);
    Route::post('/login', [LoginController::class , 'login']);
    Route::post('/forgot-password', [PasswordResetController::class , 'sendResetLink']);
    Route::post('/reset-password', [PasswordResetController::class , 'reset']);

    // Google Auth
    Route::get('/google', [\App\Http\Controllers\Api\SocialAuthController::class , 'redirectToGoogle']);
    Route::get('/google/callback', [\App\Http\Controllers\Api\SocialAuthController::class , 'handleGoogleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [LogoutController::class , 'logout']);
        }
        );
    });

/* |-------------------------------------------------------------------------- | FREE GIFTS (PUBLIC + OPTIONAL AUTH) |-------------------------------------------------------------------------- | - Guests can create gifts | - Logged-in users get sender_id stored */
Route::post('/free-gifts', [FreeGiftController::class , 'store'])
    ->middleware('optional.sanctum');

Route::get('/free-gifts/{token}', [FreeGiftController::class , 'show']);
Route::get('/free-gifts/image/{token}', [FreeGiftController::class , 'serveImage'])->name('free-gifts.image');

// Premium Gifts Proxy
Route::get('/premium-gifts/image/{token}/{filename}', [PremiumGiftController::class , 'serveImage'])->name('premium-gifts.image');

/* |-------------------------------------------------------------------------- | DASHBOARD (AUTH REQUIRED) |-------------------------------------------------------------------------- */
Route::middleware('auth:sanctum')->get(
    '/dashboard-data',
[UserDashboardController::class , 'index']
);

/* |-------------------------------------------------------------------------- | PREMIUM GIFTS |-------------------------------------------------------------------------- */

/* |-------------------------------------------------- | Public Routes |-------------------------------------------------- */

// View published gift
Route::get(
    '/premium-gifts/view/{token}',
[PremiumGiftController::class , 'viewGift']
);
// 🔥 Add teaser-check here (PUBLIC)
Route::get(
    '/premium-gifts/teaser-check/{token}',
[PremiumGiftController::class , 'teaserCheck']
);


// Verify secret answer
Route::post(
    '/premium-gifts/{token}/verify-secret',
[PremiumGiftController::class , 'verifyAndUnlock']
);
/* |--------------------------------------------------------------------------
| MEMORY MAPS
|--------------------------------------------------------------------------
*/

/* --------------------------------------------------
| Public Routes
|-------------------------------------------------- */

// View published memory map (gated inside controller)
Route::get(
    '/memory-maps/view/{token}',
    [MemoryMapController::class, 'viewMap']
);

// Verify password
Route::post(
    '/memory-maps/{token}/verify-password',
    [MemoryMapController::class, 'verifyPassword']
);

/* |-------------------------------------------------- | Authenticated Routes |-------------------------------------------------- */
Route::prefix('premium-gifts')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::post('/draft', [PremiumGiftController::class , 'createDraft']);

        Route::put('/draft/{id}', [PremiumGiftController::class , 'updateDraft']);

        Route::delete('/draft/{id}', [PremiumGiftController::class , 'deleteDraft']);

        Route::get('/preview/{token}', [PremiumGiftController::class , 'previewDraft']);

        Route::post('/{id}/images', [PremiumGiftController::class , 'uploadImages']);

        Route::post('/{id}/apply-coupon', [PremiumGiftController::class , 'applyCoupon']);

        Route::post('/{id}/publish', [PremiumGiftController::class, 'publishGift']);
        
    });
/* --------------------------------------------------
| Authenticated Routes
|-------------------------------------------------- */
        Route::post('/{id}/publish', [PremiumGiftController::class , 'publishGift']);

        // Razorpay Routes
        Route::post('/{id}/create-order', [PremiumGiftController::class , 'createOrder']);
        Route::post('/{id}/verify-payment', [PremiumGiftController::class , 'verifyPayment']);


Route::prefix('memory-maps')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::post('/draft', [MemoryMapController::class, 'createDraft']);
        Route::get('/draft/{id}', [MemoryMapController::class, 'getDraft']);
        Route::get('/manage/{id}', [MemoryMapController::class, 'getManageMap']);


        Route::post('/{id}/apply-coupon', [MemoryMapController::class, 'applyCoupon']);
        Route::post('/{id}/publish', [MemoryMapController::class, 'publishMap']);

        Route::post('/{id}/invite', [MemoryMapController::class, 'inviteParticipants']);
        Route::post('/{id}/memories', [MemoryMapController::class, 'addMemory']);
        Route::delete('/memories/{memoryId}', [MemoryMapController::class, 'deleteMemory']);
    });

