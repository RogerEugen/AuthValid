<?php
use App\Http\Controllers\AuthController;
use  Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\FacultyController;
use App\Http\Controllers\Admin\DepartmentController;
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

// ── Admin only can use this api points from the proxy───────────────────────────────
Route::prefix('admin')->middleware('auth:api')->group(function () {
    // Faculties
    Route::apiResource('faculties', FacultyController::class);
    // Departments
    Route::apiResource('departments', DepartmentController::class);
    Route::get('faculties/{facultyId}/departments', [DepartmentController::class, 'byFaculty']);
    // Programs
    Route::apiResource('programs', ProgramController::class);
    Route::get('departments/{departmentId}/programs', [ProgramController::class, 'byDepartment']);
});