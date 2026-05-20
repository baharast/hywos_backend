<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BayLineController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;

Route::prefix('companies')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.
    // Re-enable auth and permission middleware when ready by uncommenting the lines below.

    Route::get('', [CompanyController::class, 'index']);
    Route::get('/{id}', [CompanyController::class, 'show']);

    Route::post('', [CompanyController::class, 'store']);
    Route::put('/{id}', [CompanyController::class, 'update']);
    Route::patch('/{id}/activate', [CompanyController::class, 'activate']);
    Route::patch('/{id}/deactivate', [CompanyController::class, 'deactivate']);
    Route::delete('/{id}', [CompanyController::class, 'destroy']);
});

Route::prefix('users')->group(function () {
    Route::get('', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);

    Route::post('', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::patch('/{id}/activate', [UserController::class, 'activate']);
    Route::patch('/{id}/deactivate', [UserController::class, 'deactivate']);
    Route::patch('/{id}/roles', [UserController::class, 'updateRoles']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

Route::prefix('baylines')->group(function () {
    Route::get('/', function () {
        return response()->json(['message' => 'Welcome to the HYWOS API']);
    });
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.
    // Re-enable auth and permission middleware when ready by uncommenting the lines below.

    // Public read endpoints (originally protected by ['auth:sanctum', 'permission:baylines.view'])
    Route::get('', [BayLineController::class, 'index']);
    Route::get('/{id}', [BayLineController::class, 'show']);

    // Manage endpoints (originally inside ['auth:sanctum'] group and per-route permission middleware)
    Route::post('', [BayLineController::class, 'store']);
    Route::put('/{id}', [BayLineController::class, 'update']);
    Route::patch('/{id}/activate', [BayLineController::class, 'activate']);
    Route::patch('/{id}/deactivate', [BayLineController::class, 'deactivate']);
    Route::delete('/{id}', [BayLineController::class, 'destroy']);

    /*
    // To restore protection, use:
    Route::middleware(['auth:sanctum', 'permission:baylines.view'])->group(function () {
        Route::get('/baylines', [BayLineController::class, 'index']);
        Route::get('/baylines/{id}', [BayLineController::class, 'show']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/baylines', [BayLineController::class, 'store'])->middleware('permission:baylines.create');
        Route::put('/baylines/{id}', [BayLineController::class, 'update'])->middleware('permission:baylines.update');
        Route::delete('/baylines/{id}', [BayLineController::class, 'destroy'])->middleware('permission:baylines.delete');
    });
    */
});

Route::prefix('parkings')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.
    // Re-enable auth and permission middleware when ready by uncommenting the lines below.

    Route::get('', [\App\Http\Controllers\Api\ParkingController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ParkingController::class, 'show']);

    Route::post('', [\App\Http\Controllers\Api\ParkingController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\ParkingController::class, 'update']);
    Route::patch('/{id}/activate', [\App\Http\Controllers\Api\ParkingController::class, 'activate']);
    Route::patch('/{id}/deactivate', [\App\Http\Controllers\Api\ParkingController::class, 'deactivate']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\ParkingController::class, 'destroy']);
});

Route::prefix('customers')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);

    Route::post('', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
    Route::patch('/{id}/activate', [\App\Http\Controllers\Api\CustomerController::class, 'activate']);
    Route::patch('/{id}/deactivate', [\App\Http\Controllers\Api\CustomerController::class, 'deactivate']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
});
