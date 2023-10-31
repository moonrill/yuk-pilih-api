<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\VoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Define routes that require authentication
Route::middleware('auth')->group(function() {
    // Define routes for authentication
    Route::controller(AuthController::class)->prefix('auth')->group(function () {
        // Register a new user
        Route::post('register', 'register')->withoutMiddleware('auth');
        // Log in a user
        Route::post('login', 'login')->withoutMiddleware('auth');
        // Log out a user
        Route::post('logout', 'logout');
        // Get the currently authenticated user
        Route::get('me', 'me');
        // Reset a user's password
        Route::post('reset-password', 'reset');
    });
    // Define routes for managing polls
    Route::controller(PollController::class)->middleware('auth')->group(function () {
        // Get all polls
        Route::get('poll', 'getAll');
        // Create a new poll
        Route::post('poll', 'create');
        // Get a specific poll
        Route::get('poll/{id}', 'getPoll');
        // Vote on a specific choice in a poll
        Route::post('poll/{poll_id}/vote/{choice_id}', 'vote');
        // Delete a poll
        Route::delete('poll/{id}', 'delete');
    });
});
