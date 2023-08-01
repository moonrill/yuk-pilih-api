<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PollController;
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

Route::controller(AuthController::class)->prefix('auth')->group(function () {
   Route::post('register', 'register');
   Route::post('login', 'login');
   Route::post('logout', 'logout');
   Route::get('me', 'me');
   Route::post('reset-password', 'reset');
});
Route::controller(PollController::class)->group(function () {
   Route::post('poll', 'create');
});
