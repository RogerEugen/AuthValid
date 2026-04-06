<?php
use App\Http\Controllers\AuthController;
use  Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\FacultyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Registrar\CsvImportController;
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
    Route::post('/refresh-anon-token',   [AuthController::class, 'refreshAnonToken']); // ✅ new
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


// Registrar CSV import routes
Route::prefix('registrar')->middleware('auth:api')->group(function () {
    Route::get('/imports',          [CsvImportController::class, 'index']);
    Route::post('/import/students', [CsvImportController::class, 'importStudents']);
    Route::post('/import/staff',    [CsvImportController::class, 'importStaff']);
    Route::get('/imports/{uuid}',   [CsvImportController::class, 'show']);
});
// Token validation — used by feedback service
Route::post('/token/validate', [AuthController::class, 'validateAnonToken']);