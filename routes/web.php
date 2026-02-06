<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Controllers
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminManagementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| These routes use the "web" middleware group.
| Admin auth is completely isolated from normal users.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ADMIN AUTH (NO AUTH REQUIRED)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {

    // Admin login page
    Route::get('/login', [AdminAuthController::class, 'showLogin'])
        ->name('admin.login');

    // Admin login submit
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->name('admin.login.submit');
});

/*
|--------------------------------------------------------------------------
| ADMIN PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware('auth:admin')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');

        // CSV Export
        Route::get('/export/gifts', [AdminDashboardController::class, 'exportCsv'])
            ->name('admin.export.csv');

        // Logout
        Route::post('/logout', [AdminAuthController::class, 'logout'])
            ->name('admin.logout');

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMIN ONLY
        |--------------------------------------------------------------------------
        */
        Route::middleware('super.admin')->group(function () {

            // Admin management page
            Route::get('/admins', [AdminManagementController::class, 'index'])
                ->name('admin.admins');

            // Create admin
            Route::post('/admins', [AdminManagementController::class, 'store']);

            // Update admin password
            Route::post('/admins/{admin}/password',
                [AdminManagementController::class, 'updatePassword']
            );

            // Enable / Disable admin
            Route::post('/admins/{admin}/toggle',
                [AdminManagementController::class, 'toggle']
            );

            // Delete admin (with confirm on frontend)
            Route::delete('/admins/{admin}',
                [AdminManagementController::class, 'destroy']
            );
        });
    });

/*
|--------------------------------------------------------------------------
| PUBLIC / DEFAULT ROUTES
|--------------------------------------------------------------------------
*/

// Route::get('/test-welcome-mail', function () {
//     $user = (object) [
//         'full_name' => 'Test User',
//         'email' => 'test@example.com',
//     ];
//
//     Mail::to($user->email)->send(new WelcomeUserMail($user));
//
//     return 'Welcome email sent';
// });

Route::get('/', function () {
    return view('welcome');
});
