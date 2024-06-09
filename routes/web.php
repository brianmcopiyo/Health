<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\pages\HomePage;
use App\Http\Controllers\pages\Page2;
use App\Http\Controllers\pages\MiscError;
use App\Http\Controllers\authentications\LoginBasic;
use App\Http\Controllers\authentications\RegisterBasic;

// Authentication routes
Route::prefix('auth')->group(
    function () {
        // authentication
        Route::get('/login', [LoginBasic::class, 'index'])->name('login');
        Route::get('/register', [RegisterBasic::class, 'index'])->name('register');
    }
);

// Routes accessible only to authenticated users
Route::middleware('auth')->group(
    function () {
        // Main Page Route
        Route::get('/', [HomePage::class, 'index'])->name('pages-home');
        Route::get('/page-2', [Page2::class, 'index'])->name('pages-page-2');

        // locale
        Route::get('lang/{locale}', [LanguageController::class, 'swap']);
    }
);

// pages
Route::get('/error', [MiscError::class, 'index'])->name('error');

Route::get('{any}', function () {
    // Redirect to the error page
    return redirect('error');
})->where('any', '.*');
