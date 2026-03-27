<?php
use App\Http\Controllers\AuthController;
use  Illuminate\Support\Facades\Route;

// Public routes — no token needed
Route::prefix('auth')->group(function () {
    Route::post('/login',   [AuthController::class, 'login']);
});

// Protected routes — JWT required
Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::post('/refresh',         [AuthController::class, 'refresh']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/me',               [AuthController::class, 'me']);
});