<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here carry the /api prefix automatically.
|
*/

// --- Public ---
Route::get('/health', HealthController::class);

// --- Authentication ---
Route::prefix('auth')->group(function () {

    // Public auth endpoints — rate-limited to protect against brute force.
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.reset');

    // Protected auth endpoints — require valid Sanctum token.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// --- Email Verification ---
// These routes require authentication (user must be logged in to verify/resend).
Route::middleware('auth:sanctum')->prefix('email')->group(function () {
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// --- Profile & Interests ---
Route::get('/interests', [\App\Http\Controllers\InterestController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update']);
    Route::put('/profile/interests', [\App\Http\Controllers\ProfileController::class, 'updateInterests']);
    Route::post('/profile/photo', [\App\Http\Controllers\ProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [\App\Http\Controllers\ProfileController::class, 'deletePhoto']);
});

// --- Trips ---
Route::middleware('auth:sanctum')->group(function () {
    // My trips list (paginated, owner's trips only)
    Route::get('/my/trips', [\App\Http\Controllers\TripController::class, 'myTrips']);

    // Trip CRUD
    Route::post('/trips', [\App\Http\Controllers\TripController::class, 'store']);
    Route::get('/trips/{trip}', [\App\Http\Controllers\TripController::class, 'show']);
    Route::put('/trips/{trip}', [\App\Http\Controllers\TripController::class, 'update']);

    // Trip lifecycle
    Route::post('/trips/{trip}/publish', [\App\Http\Controllers\TripController::class, 'publish']);
    Route::post('/trips/{trip}/cancel', [\App\Http\Controllers\TripController::class, 'cancel']);
});


