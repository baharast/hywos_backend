<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BayLineController;

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
