<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BayLineController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\LoadingControlController;
use App\Http\Controllers\Api\PlantConfigurationController;
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

    // Critical lifecycle actions (POST + reason + audit + event)
    Route::post('/{id}/disable', [UserController::class, 'disable']);
    Route::post('/{id}/enable', [UserController::class, 'enable']);
    Route::post('/{id}/lock', [UserController::class, 'lock']);
    Route::post('/{id}/unlock', [UserController::class, 'unlock']);
    Route::post('/{id}/reset-access', [UserController::class, 'resetAccess']);

    // @deprecated PATCH wrappers kept for FE transition; prefer the POST endpoints above.
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

    // Block/unblock are critical actions (POST + required reason + audit + event).
    // activate/deactivate remain for the active<->inactive lifecycle (status field) separately.
    Route::post('/{id}/block', [\App\Http\Controllers\Api\CustomerController::class, 'block']);
    Route::post('/{id}/unblock', [\App\Http\Controllers\Api\CustomerController::class, 'unblock']);

    Route::patch('/{id}/activate', [\App\Http\Controllers\Api\CustomerController::class, 'activate']);
    Route::patch('/{id}/deactivate', [\App\Http\Controllers\Api\CustomerController::class, 'deactivate']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
});

Route::prefix('plant-configuration')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    // Read current configuration + counts + validation summary
    Route::get('', [PlantConfigurationController::class, 'show']);

    // Draft lifecycle
    Route::post('/draft', [PlantConfigurationController::class, 'startDraft']);
    Route::put('/draft', [PlantConfigurationController::class, 'updateDraft']);

    // Validation + review + activation
    Route::post('/validate', [PlantConfigurationController::class, 'validateConfiguration']);
    Route::get('/review', [PlantConfigurationController::class, 'review']);
    Route::post('/activate', [PlantConfigurationController::class, 'activate']);

    // Structural object creation (during draft only — service rejects when locked)
    Route::post('/areas', [PlantConfigurationController::class, 'storeArea']);
    Route::post('/gates', [PlantConfigurationController::class, 'storeGate']);
    Route::post('/terminals', [PlantConfigurationController::class, 'storeTerminal']);

    // Detail for a single configured object by type+id
    Route::get('/objects/{type}/{id}', [PlantConfigurationController::class, 'showObject']);

    // Change request flow (only on active/locked configuration)
    Route::post('/change-requests', [PlantConfigurationController::class, 'submitChangeRequest']);
    Route::get('/change-requests/{id}', [PlantConfigurationController::class, 'showChangeRequest']);
    Route::post('/change-requests/{id}/approve', [PlantConfigurationController::class, 'approveChangeRequest']);
    Route::post('/change-requests/{id}/reject', [PlantConfigurationController::class, 'rejectChangeRequest']);
    Route::post('/change-requests/{id}/apply', [PlantConfigurationController::class, 'applyChangeRequest']);
});

Route::prefix('drivers')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    // List/detail
    Route::get('', [DriverController::class, 'index']);
    Route::post('/export', [DriverController::class, 'export']);
    Route::get('/{id}', [DriverController::class, 'show']);

    // Create/update
    Route::post('', [DriverController::class, 'store']);
    Route::put('/{id}', [DriverController::class, 'update']);

    // Critical actions (POST + required reason + audit + event)
    Route::post('/{id}/block', [DriverController::class, 'block']);
    Route::post('/{id}/unblock', [DriverController::class, 'unblock']);

    // Identification (chip cards + TANs)
    Route::get('/{id}/auth-media', [DriverController::class, 'authMedia']);
    Route::post('/{id}/tan', [DriverController::class, 'createTan']);

    // Detail tabs (placeholders for modules not yet implemented)
    Route::get('/{id}/plant-visits', [DriverController::class, 'plantVisits']);
    Route::get('/{id}/events-audit', [DriverController::class, 'eventsAudit']);
});

Route::prefix('loading-control')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('/station-view', [LoadingControlController::class, 'stationView']);
    Route::get('/active-loadings', [LoadingControlController::class, 'activeLoadings']);

    Route::get('/loadings/{id}', [LoadingControlController::class, 'show']);
    Route::get('/loadings/{id}/events', [LoadingControlController::class, 'events']);
    Route::get('/loadings/{id}/audit', [LoadingControlController::class, 'audit']);
    Route::post('/loadings/{id}/notes', [LoadingControlController::class, 'addNote']);
});
