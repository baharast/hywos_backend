<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BayLineController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\ChipCardController;
use App\Http\Controllers\Api\ClarificationCaseController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\GateTerminalMonitorController;
use App\Http\Controllers\Api\LoadingControlController;
use App\Http\Controllers\Api\LoadingOrderController;
use App\Http\Controllers\Api\MasterDataExportController;
use App\Http\Controllers\Api\OperationalDocumentController;
use App\Http\Controllers\Api\PlantConfigurationController;
use App\Http\Controllers\Api\PlantVisitController;
use App\Http\Controllers\Api\TanController;
use App\Http\Controllers\Api\TractorVehicleController;
use App\Http\Controllers\Api\TrailerController;
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

    // Lifecycle actions (V2.1 §16.1) — POST + required reason + audit + event.
    Route::post('/{id}/reserve', [\App\Http\Controllers\Api\ParkingController::class, 'reserve']);
    Route::post('/{id}/occupy', [\App\Http\Controllers\Api\ParkingController::class, 'occupy']);
    Route::post('/{id}/clear', [\App\Http\Controllers\Api\ParkingController::class, 'clear']);
    Route::post('/{id}/block', [\App\Http\Controllers\Api\ParkingController::class, 'block']);
    Route::post('/{id}/unblock', [\App\Http\Controllers\Api\ParkingController::class, 'unblock']);
    Route::post('/{id}/out-of-service', [\App\Http\Controllers\Api\ParkingController::class, 'outOfService']);
    Route::post('/{id}/restore', [\App\Http\Controllers\Api\ParkingController::class, 'restore']);
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

Route::prefix('trailers')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [TrailerController::class, 'index']);
    Route::post('/export', [TrailerController::class, 'export']);
    Route::get('/{id}', [TrailerController::class, 'show']);

    Route::post('', [TrailerController::class, 'store']);
    Route::put('/{id}', [TrailerController::class, 'update']);

    Route::post('/{id}/block', [TrailerController::class, 'block']);
    Route::post('/{id}/unblock', [TrailerController::class, 'unblock']);

    Route::get('/{id}/auth-media', [TrailerController::class, 'authMedia']);

    Route::get('/{id}/plant-visits', [TrailerController::class, 'plantVisits']);
    Route::get('/{id}/loadings', [TrailerController::class, 'loadings']);
    Route::get('/{id}/documents', [TrailerController::class, 'documents']);
    Route::get('/{id}/events-audit', [TrailerController::class, 'eventsAudit']);
});

Route::prefix('freight-forwarders-carriers')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [CarrierController::class, 'index']);
    Route::post('/export', [CarrierController::class, 'export']);
    Route::get('/{id}', [CarrierController::class, 'show']);

    Route::post('', [CarrierController::class, 'store']);
    Route::put('/{id}', [CarrierController::class, 'update']);

    // Critical actions (POST + required reason + audit + event)
    Route::post('/{id}/block', [CarrierController::class, 'block']);
    Route::post('/{id}/unblock', [CarrierController::class, 'unblock']);

    // Related records tabs
    Route::get('/{id}/drivers', [CarrierController::class, 'drivers']);
    Route::get('/{id}/vehicles', [CarrierController::class, 'vehicles']);
    Route::get('/{id}/trailers', [CarrierController::class, 'trailers']);
    Route::get('/{id}/orders', [CarrierController::class, 'orders']);
    Route::get('/{id}/plant-visits', [CarrierController::class, 'plantVisits']);
    Route::get('/{id}/events-audit', [CarrierController::class, 'eventsAudit']);
});

Route::prefix('tractors-vehicles')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [TractorVehicleController::class, 'index']);
    Route::post('/export', [TractorVehicleController::class, 'export']);
    Route::get('/{id}', [TractorVehicleController::class, 'show']);

    Route::post('', [TractorVehicleController::class, 'store']);
    Route::put('/{id}', [TractorVehicleController::class, 'update']);

    // Critical actions (POST + required reason + audit + event)
    Route::post('/{id}/block', [TractorVehicleController::class, 'block']);
    Route::post('/{id}/unblock', [TractorVehicleController::class, 'unblock']);

    // History tabs
    Route::get('/{id}/couplings', [TractorVehicleController::class, 'couplings']);
    Route::get('/{id}/plant-visits', [TractorVehicleController::class, 'plantVisits']);
    Route::get('/{id}/clarifications', [TractorVehicleController::class, 'clarifications']);
    Route::get('/{id}/events-audit', [TractorVehicleController::class, 'eventsAudit']);
});

Route::prefix('master-data-export')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    // Recent exports list + summary counts
    Route::get('', [MasterDataExportController::class, 'index']);

    // Create + run a new export job (synchronous in dev; queue-ready)
    Route::post('', [MasterDataExportController::class, 'store']);

    // Single job detail
    Route::get('/{id}', [MasterDataExportController::class, 'show']);

    // Download the generated file when status=ready
    Route::get('/{id}/download', [MasterDataExportController::class, 'download']);

    // Retry a failed job; produces a new job with same setup
    Route::post('/{id}/retry', [MasterDataExportController::class, 'retry']);
});

Route::prefix('chip-cards')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [ChipCardController::class, 'index']);
    Route::post('/export', [ChipCardController::class, 'export']);
    Route::get('/{id}', [ChipCardController::class, 'show']);

    Route::post('', [ChipCardController::class, 'store']);
    Route::put('/{id}', [ChipCardController::class, 'update']);

    // Assignment
    Route::post('/{id}/assign', [ChipCardController::class, 'assign']);
    Route::post('/{id}/unassign', [ChipCardController::class, 'unassign']);

    // Lifecycle (POST + required reason + audit + event)
    Route::post('/{id}/block', [ChipCardController::class, 'block']);
    Route::post('/{id}/unblock', [ChipCardController::class, 'unblock']);
    Route::post('/{id}/mark-lost', [ChipCardController::class, 'markLost']);
    Route::post('/{id}/mark-defective', [ChipCardController::class, 'markDefective']);
    Route::post('/{id}/replace', [ChipCardController::class, 'replace']);
    Route::post('/{id}/archive', [ChipCardController::class, 'archive']);

    // History tabs
    Route::get('/{id}/assignment-history', [ChipCardController::class, 'assignmentHistory']);
    Route::get('/{id}/usage-history', [ChipCardController::class, 'usageHistory']);
    Route::get('/{id}/events-audit', [ChipCardController::class, 'eventsAudit']);
});

Route::prefix('tans')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [TanController::class, 'index']);
    Route::post('/export', [TanController::class, 'export']);
    Route::get('/{id}', [TanController::class, 'show']);

    // Create (generate) — returns oneTimeFullValue exactly once
    Route::post('', [TanController::class, 'store']);

    // Lifecycle (POST + required reason + audit + event)
    Route::post('/{id}/revoke', [TanController::class, 'revoke']);
    Route::post('/{id}/expire-now', [TanController::class, 'expireNow']);

    // History tabs
    Route::get('/{id}/usage-history', [TanController::class, 'usageHistory']);
    Route::get('/{id}/security-events', [TanController::class, 'securityEvents']);
    Route::get('/{id}/events-audit', [TanController::class, 'eventsAudit']);
});

Route::prefix('clarification-cases')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [ClarificationCaseController::class, 'index']);
    Route::get('/{id}', [ClarificationCaseController::class, 'show']);
    Route::post('', [ClarificationCaseController::class, 'store']);

    // Lifecycle (V1.3 §4.1) — POST + audit + event. No `cancel` endpoint
    // exists because V1.3 has no `cancelled` status.
    Route::post('/{id}/acknowledge', [ClarificationCaseController::class, 'acknowledge']);
    Route::post('/{id}/assign', [ClarificationCaseController::class, 'assign']);
    Route::post('/{id}/move-to-waiting-for-owner', [ClarificationCaseController::class, 'moveToWaitingForOwner']);
    Route::post('/{id}/resolve', [ClarificationCaseController::class, 'resolve']);
    Route::post('/{id}/close', [ClarificationCaseController::class, 'close']);
});

Route::prefix('loading-orders')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [LoadingOrderController::class, 'index']);
    Route::get('/{id}', [LoadingOrderController::class, 'show']);
    Route::post('', [LoadingOrderController::class, 'store']);
    Route::put('/{id}', [LoadingOrderController::class, 'update']);

    // Assignment (no reason required — these are dispatcher actions, not critical state changes)
    Route::post('/{id}/assign-driver', [LoadingOrderController::class, 'assignDriver']);
    Route::post('/{id}/unassign-driver', [LoadingOrderController::class, 'unassignDriver']);
    Route::post('/{id}/assign-trailer', [LoadingOrderController::class, 'assignTrailer']);
    Route::post('/{id}/unassign-trailer', [LoadingOrderController::class, 'unassignTrailer']);

    // Lifecycle (POST + reason + audit + event)
    Route::post('/{id}/block', [LoadingOrderController::class, 'block']);
    Route::post('/{id}/unblock', [LoadingOrderController::class, 'unblock']);
    Route::post('/{id}/cancel', [LoadingOrderController::class, 'cancel']);

    // Timeline
    Route::get('/{id}/events-audit', [LoadingOrderController::class, 'eventsAudit']);
});

Route::prefix('plant-visits')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.

    Route::get('', [PlantVisitController::class, 'index']);
    Route::get('/{id}', [PlantVisitController::class, 'show']);
    Route::post('', [PlantVisitController::class, 'store']);

    // Step + location lifecycle
    Route::post('/{id}/advance-step', [PlantVisitController::class, 'advanceStep']);
    Route::post('/{id}/change-location', [PlantVisitController::class, 'changeLocation']);

    // Status transitions (POST + audit + event; reason required on
    // wait/block/raise-clarification/force-close)
    Route::post('/{id}/wait', [PlantVisitController::class, 'wait']);
    Route::post('/{id}/resume', [PlantVisitController::class, 'resume']);
    Route::post('/{id}/block', [PlantVisitController::class, 'block']);
    Route::post('/{id}/unblock', [PlantVisitController::class, 'unblock']);
    Route::post('/{id}/raise-clarification', [PlantVisitController::class, 'raiseClarification']);
    Route::post('/{id}/mark-ready-for-exit', [PlantVisitController::class, 'markReadyForExit']);
    Route::post('/{id}/close', [PlantVisitController::class, 'close']);
    Route::post('/{id}/force-close', [PlantVisitController::class, 'forceClose']);

    // Timeline
    Route::get('/{id}/events-audit', [PlantVisitController::class, 'eventsAudit']);
});

Route::prefix('sap-sync')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.
    //
    // SAP Sync / Order Import Status is STRICTLY read-only in MVP per V1.5 §2.2:
    //   - no POST /retry (V1.5 §4.2 — "no manual retry action in MVP")
    //   - no order creation / editing / assignment (belongs in /loading-orders)
    //   - no DELETE, no bulk, no quality / document decisions
    // Write endpoints arrive only when the SAP_SYNC_* audit constants in
    // App\Enums\AuditAction get a real emitter.

    Route::get('', [\App\Http\Controllers\Api\SapSyncController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\SapSyncController::class, 'show']);
});

Route::prefix('gate-terminal-monitor')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.
    //
    // Read-only per Gate & Terminal Monitor V2.3 §2.2:
    //   - no remote gate opening, force exit, or PLC/ESD commands
    //   - no order assignment, quality decision, or document handling here
    //   - no bulk, no DELETE, no POST in this slice
    // Write endpoints arrive only when the GATE_TERMINAL_* audit constants
    // in App\Enums\AuditAction get a real emitter.

    Route::get('/touchpoints', [GateTerminalMonitorController::class, 'touchpoints']);
    Route::get('/sessions', [GateTerminalMonitorController::class, 'sessions']);
    Route::get('/sessions/{id}', [GateTerminalMonitorController::class, 'show']);
});

Route::prefix('documents-reports/operational-documents')->group(function () {
    // NOTE: Middleware temporarily disabled so endpoints are publicly accessible for development.
    //
    // Operational Documents V1.2 — certificates, delivery notes and QM
    // documents. Lifecycle actions (print/reprint/handover/invalidate)
    // are auditable; reprint and invalidate require a reason per V1.2
    // §13 + §18. No bulk actions and no hard delete (V1.2 §2.2).

    Route::get('', [OperationalDocumentController::class, 'index']);
    Route::get('/{id}', [OperationalDocumentController::class, 'show']);
    Route::get('/{id}/preview', [OperationalDocumentController::class, 'preview']);
    Route::get('/{id}/print-history', [OperationalDocumentController::class, 'printHistory']);

    Route::post('/{id}/print', [OperationalDocumentController::class, 'print']);
    Route::post('/{id}/reprint', [OperationalDocumentController::class, 'reprint']);
    Route::post('/{id}/hand-over', [OperationalDocumentController::class, 'handOver']);
    Route::post('/{id}/invalidate', [OperationalDocumentController::class, 'invalidate']);
});
