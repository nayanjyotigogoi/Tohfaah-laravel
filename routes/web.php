<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeUserMail;

Route::get('/test-welcome-mail', function () {
    $user = (object) [
        'full_name' => 'Test User',
        'email' => 'nayanjyoti2724@gmail.com',
    ];

    Mail::to($user->email)->send(new WelcomeUserMail($user));

    return 'Welcome email sent';
});

Route::get('/', function () {
    return view('welcome');
});
