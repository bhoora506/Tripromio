<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PreferredDestinationController;
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
    // Profile Management
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/interests', [ProfileController::class, 'updateInterests']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);

    // Preferred Destinations
    Route::apiResource('profile/destinations', PreferredDestinationController::class)
         ->parameter('destinations', 'destination')
         ->except(['show']);

    // Travel Availability — user's own windows only
    Route::get('/profile/availability', [\App\Http\Controllers\TravelAvailabilityController::class, 'index']);
    Route::post('/profile/availability', [\App\Http\Controllers\TravelAvailabilityController::class, 'store']);
    Route::put('/profile/availability/{availability}', [\App\Http\Controllers\TravelAvailabilityController::class, 'update']);
    Route::delete('/profile/availability/{availability}', [\App\Http\Controllers\TravelAvailabilityController::class, 'destroy']);
});

// --- Trips ---
Route::middleware('auth:sanctum')->group(function () {
    // My trips list (paginated, owner's trips only)
    Route::get('/my/trips', [\App\Http\Controllers\TripController::class, 'myTrips']);

    // Trip discovery (published trips from other users, paginated + filtered)
    Route::get('/trips', [\App\Http\Controllers\TripController::class, 'index']);

    // Trip CRUD
    Route::post('/trips', [\App\Http\Controllers\TripController::class, 'store']);
    Route::get('/trips/{trip}', [\App\Http\Controllers\TripController::class, 'show']);
    Route::put('/trips/{trip}', [\App\Http\Controllers\TripController::class, 'update']);

    // Trip lifecycle
    Route::post('/trips/{trip}/publish', [\App\Http\Controllers\TripController::class, 'publish']);
    Route::post('/trips/{trip}/cancel', [\App\Http\Controllers\TripController::class, 'cancel']);
});


