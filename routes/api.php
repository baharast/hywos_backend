<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ActiveAnalysisController;
use App\Http\Controllers\Api\AnalysisDeviceController;
use App\Http\Controllers\Api\BayLineController;
use App\Http\Controllers\Api\CalibrationProfileController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\ChipCardController;
use App\Http\Controllers\Api\ClarificationCaseController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverWorkflowController;
use App\Http\Controllers\Api\GateTerminalMonitorController;
use App\Http\Controllers\Api\HardwareDeviceController;
use App\Http\Controllers\Api\InterfaceHealthController;
use App\Http\Controllers\Api\LoadingControlController;
use App\Http\Controllers\Api\LoadingOrderController;
use App\Http\Controllers\Api\MasterDataExportController;
use App\Http\Controllers\Api\OperationalDocumentController;
use App\Http\Controllers\Api\PlantConfigurationController;
use App\Http\Controllers\Api\PlantVisitController;
use App\Http\Controllers\Api\PrinterTabController;
use App\Http\Controllers\Api\ProductSpecificationController;
use App\Http\Controllers\Api\QualityDecisionController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\SafetyTrainingController;
use App\Http\Controllers\Api\TanController;
use App\Http\Controllers\Api\TerminalAuthController;
use App\Http\Controllers\Api\TerminalPanelTabController;
use App\Http\Controllers\Api\TractorVehicleController;
use App\Http\Controllers\Api\TrailerController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| Routing & Access Rules
|--------------------------------------------------------------------------
|
| Two access tiers:
|
|   1. PUBLIC — `/api/terminal/*`
|      Driver terminal login (V3). Identity is ESTABLISHED here:
|        - GET  /config/{terminal}        terminal config (no auth)
|        - GET  /safety-info              safety bullets (no auth)
|        - POST /auth/tan                 driver TAN login (no auth)
|        - POST /auth/chip                driver chip login (no auth)
|        - POST /auth/manager-logon       Manager popup → Sanctum token
|        - GET  /session/{id}             driver checks own session
|        - POST /session/{id}/language    driver changes language
|        - POST /session/{id}/logout      driver logs out manually
|      Driver session id in the URL is the credential — Sanctum is for
|      dashboard users, not for driver terminals (drivers have no users row).
|
|   2. SANCTUM-PROTECTED — everything else
|      Requires `Authorization: Bearer <token>` (token returned by
|      `POST /api/terminal/auth/manager-logon`). Each route then asserts
|      one Spatie permission seeded by RolePermissionSeeder.
|
| Role → permission map lives in `database/seeders/RolePermissionSeeder.php`
| (constants `PERMISSIONS_BY_MODULE` and `ROLE_PERMISSIONS`). The 7 demo
| users seeded by AdminUserSeeder cover one role each plus locked/disabled
| accounts for the V3 §11.3 error paths.
*/

/*
|--------------------------------------------------------------------------
| Public — Driver Terminal Login (V3)
|--------------------------------------------------------------------------
*/

Route::prefix('terminal')->group(function () {
    // Terminal info + safety content — accessible before authentication so
    // the kiosk page can render the welcome screen.
    Route::get('/config/{terminal}', [TerminalAuthController::class, 'config']);
    Route::get('/safety-info', [TerminalAuthController::class, 'safetyInfo']);

    // Driver identification — TAN / chip / Tab-simulation chip
    Route::post('/auth/tan', [TerminalAuthController::class, 'authenticateTan']);
    Route::post('/auth/chip', [TerminalAuthController::class, 'authenticateChip']);

    // Manager / Operator popup (V3 §11) — issues a Sanctum personal access
    // token on success. This is the only entry point that returns a token
    // usable for the protected routes below.
    Route::post('/auth/manager-logon', [TerminalAuthController::class, 'managerLogon']);

    // Driver session lifecycle — identity is the session id in the URL.
    // Service layer validates the session exists; no Sanctum (drivers are
    // not dashboard users).
    Route::get('/session/{id}', [TerminalAuthController::class, 'session']);
    Route::post('/session/{id}/language', [TerminalAuthController::class, 'changeLanguage']);
    Route::post('/session/{id}/logout', [TerminalAuthController::class, 'logout']);

    // Driver post-login workflow (V6 §8). Same auth model as the lifecycle
    // routes above — the session id IS the identity; no Sanctum.
    // DriverWorkflowController rejects unknown / logged-out sessions with
    // 404 SESSION_NOT_FOUND via DriverWorkflowService::findActiveSession().
    Route::get('/session/{id}/orders', [DriverWorkflowController::class, 'orders']);
    Route::post('/session/{id}/trailer-info', [DriverWorkflowController::class, 'trailerInfo']);
    Route::post('/session/{id}/trailer-check', [DriverWorkflowController::class, 'trailerCheck']);
    Route::post('/session/{id}/tractor-plate', [DriverWorkflowController::class, 'tractorPlate']);
    Route::post('/session/{id}/task', [DriverWorkflowController::class, 'task']);
    Route::post('/session/{id}/order', [DriverWorkflowController::class, 'confirmOrder']);
    Route::get('/session/{id}/bay-line-assignment', [DriverWorkflowController::class, 'bayLineAssignment']);
    Route::post('/session/{id}/print-delivery-note', [DriverWorkflowController::class, 'printDeliveryNote']);
    Route::post('/session/{id}/complete', [DriverWorkflowController::class, 'complete']);

    // Driver Safety Training (V6 §7). Catalog endpoints are anonymous so
    // the kiosk overview/exam pages can render before a session is bound;
    // the two writes use the session id in the URL as credential, same
    // pattern as `/session/{id}/logout`.
    Route::get('/safety-training/modules', [SafetyTrainingController::class, 'modules']);
    Route::get('/safety-training/modules/{moduleId}', [SafetyTrainingController::class, 'module']);
    Route::get('/safety-training/exam', [SafetyTrainingController::class, 'exam']);

    Route::post('/session/{id}/training/module-completed', [SafetyTrainingController::class, 'markModuleCompleted']);
    Route::post('/session/{id}/training/exam-submitted', [SafetyTrainingController::class, 'submitExam']);
});

/*
|--------------------------------------------------------------------------
| Protected — Dashboard (Sanctum + Spatie permission)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
     * Companies — Master Data
     * Permissions: companies.view (read) / companies.manage (write)
     */
    Route::prefix('companies')->group(function () {
        Route::get('', [CompanyController::class, 'index'])->middleware('permission:companies.view');
        Route::get('/{id}', [CompanyController::class, 'show'])->middleware('permission:companies.view');

        Route::post('', [CompanyController::class, 'store'])->middleware('permission:companies.manage');
        Route::put('/{id}', [CompanyController::class, 'update'])->middleware('permission:companies.manage');
        Route::patch('/{id}/activate', [CompanyController::class, 'activate'])->middleware('permission:companies.manage');
        Route::patch('/{id}/deactivate', [CompanyController::class, 'deactivate'])->middleware('permission:companies.manage');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->middleware('permission:companies.manage');
    });

    /*
     * Users — Administration
     * Permissions: users.view (read) / users.manage (write + lifecycle)
     */
    Route::prefix('users')->group(function () {
        Route::get('', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:users.view');

        Route::post('', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.manage');

        Route::post('/{id}/disable', [UserController::class, 'disable'])->middleware('permission:users.manage');
        Route::post('/{id}/enable', [UserController::class, 'enable'])->middleware('permission:users.manage');
        Route::post('/{id}/lock', [UserController::class, 'lock'])->middleware('permission:users.manage');
        Route::post('/{id}/unlock', [UserController::class, 'unlock'])->middleware('permission:users.manage');
        Route::post('/{id}/reset-access', [UserController::class, 'resetAccess'])->middleware('permission:users.manage');

        Route::patch('/{id}/activate', [UserController::class, 'activate'])->middleware('permission:users.manage');
        Route::patch('/{id}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.manage');
        Route::patch('/{id}/roles', [UserController::class, 'updateRoles'])->middleware('permission:users.manage');
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
    });

    /*
     * BayLines — operational state for Loading Control
     * Permissions: loading_control.view / loading_control.manage
     */
    Route::prefix('baylines')->group(function () {
        Route::get('/', function () {
            return response()->json(['message' => 'Welcome to the HYWOS API']);
        });

        Route::get('', [BayLineController::class, 'index'])->middleware('permission:loading_control.view');
        Route::get('/{id}', [BayLineController::class, 'show'])->middleware('permission:loading_control.view');

        Route::post('', [BayLineController::class, 'store'])->middleware('permission:loading_control.manage');
        Route::put('/{id}', [BayLineController::class, 'update'])->middleware('permission:loading_control.manage');
        Route::patch('/{id}/activate', [BayLineController::class, 'activate'])->middleware('permission:loading_control.manage');
        Route::patch('/{id}/deactivate', [BayLineController::class, 'deactivate'])->middleware('permission:loading_control.manage');
        Route::delete('/{id}', [BayLineController::class, 'destroy'])->middleware('permission:loading_control.manage');
    });

    /*
     * Parkings / Trailer Pool — Operations
     * Permissions: plant_visits.view / plant_visits.manage
     */
    Route::prefix('parkings')->group(function () {
        Route::get('', [\App\Http\Controllers\Api\ParkingController::class, 'index'])->middleware('permission:plant_visits.view');
        Route::get('/{id}', [\App\Http\Controllers\Api\ParkingController::class, 'show'])->middleware('permission:plant_visits.view');

        Route::post('', [\App\Http\Controllers\Api\ParkingController::class, 'store'])->middleware('permission:plant_visits.manage');
        Route::put('/{id}', [\App\Http\Controllers\Api\ParkingController::class, 'update'])->middleware('permission:plant_visits.manage');
        Route::patch('/{id}/activate', [\App\Http\Controllers\Api\ParkingController::class, 'activate'])->middleware('permission:plant_visits.manage');
        Route::patch('/{id}/deactivate', [\App\Http\Controllers\Api\ParkingController::class, 'deactivate'])->middleware('permission:plant_visits.manage');
        Route::delete('/{id}', [\App\Http\Controllers\Api\ParkingController::class, 'destroy'])->middleware('permission:plant_visits.manage');

        // Lifecycle actions (V2.1 §16.1) — POST + required reason + audit + event.
        Route::post('/{id}/reserve', [\App\Http\Controllers\Api\ParkingController::class, 'reserve'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/occupy', [\App\Http\Controllers\Api\ParkingController::class, 'occupy'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/clear', [\App\Http\Controllers\Api\ParkingController::class, 'clear'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/block', [\App\Http\Controllers\Api\ParkingController::class, 'block'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/unblock', [\App\Http\Controllers\Api\ParkingController::class, 'unblock'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/out-of-service', [\App\Http\Controllers\Api\ParkingController::class, 'outOfService'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/restore', [\App\Http\Controllers\Api\ParkingController::class, 'restore'])->middleware('permission:plant_visits.manage');
    });

    /*
     * Customers — Master Data
     * Permissions: customers.view / customers.update (edit) / customers.manage (critical)
     */
    Route::prefix('customers')->group(function () {
        Route::get('', [\App\Http\Controllers\Api\CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::get('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'show'])->middleware('permission:customers.view');

        Route::post('', [\App\Http\Controllers\Api\CustomerController::class, 'store'])->middleware('permission:customers.update');
        Route::put('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update'])->middleware('permission:customers.update');

        // Critical (V1 §10.2): block/unblock + activate/deactivate + delete
        Route::post('/{id}/block', [\App\Http\Controllers\Api\CustomerController::class, 'block'])->middleware('permission:customers.manage');
        Route::post('/{id}/unblock', [\App\Http\Controllers\Api\CustomerController::class, 'unblock'])->middleware('permission:customers.manage');
        Route::patch('/{id}/activate', [\App\Http\Controllers\Api\CustomerController::class, 'activate'])->middleware('permission:customers.manage');
        Route::patch('/{id}/deactivate', [\App\Http\Controllers\Api\CustomerController::class, 'deactivate'])->middleware('permission:customers.manage');
        Route::delete('/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy'])->middleware('permission:customers.manage');
    });

    /*
     * Plant Configuration — Administration
     * Permissions: plant_configuration.view (read) / plant_configuration.manage (write)
     */
    Route::prefix('plant-configuration')->group(function () {
        Route::get('', [PlantConfigurationController::class, 'show'])->middleware('permission:plant_configuration.view');
        Route::get('/review', [PlantConfigurationController::class, 'review'])->middleware('permission:plant_configuration.view');
        Route::get('/objects/{type}/{id}', [PlantConfigurationController::class, 'showObject'])->middleware('permission:plant_configuration.view');
        Route::get('/change-requests/{id}', [PlantConfigurationController::class, 'showChangeRequest'])->middleware('permission:plant_configuration.view');

        // Draft lifecycle + structural creation + activation — all critical
        Route::post('/draft', [PlantConfigurationController::class, 'startDraft'])->middleware('permission:plant_configuration.manage');
        Route::put('/draft', [PlantConfigurationController::class, 'updateDraft'])->middleware('permission:plant_configuration.manage');
        Route::post('/validate', [PlantConfigurationController::class, 'validateConfiguration'])->middleware('permission:plant_configuration.manage');
        Route::post('/activate', [PlantConfigurationController::class, 'activate'])->middleware('permission:plant_configuration.manage');
        Route::post('/areas', [PlantConfigurationController::class, 'storeArea'])->middleware('permission:plant_configuration.manage');
        Route::post('/gates', [PlantConfigurationController::class, 'storeGate'])->middleware('permission:plant_configuration.manage');
        Route::post('/terminals', [PlantConfigurationController::class, 'storeTerminal'])->middleware('permission:plant_configuration.manage');

        Route::post('/change-requests', [PlantConfigurationController::class, 'submitChangeRequest'])->middleware('permission:plant_configuration.manage');
        Route::post('/change-requests/{id}/approve', [PlantConfigurationController::class, 'approveChangeRequest'])->middleware('permission:plant_configuration.manage');
        Route::post('/change-requests/{id}/reject', [PlantConfigurationController::class, 'rejectChangeRequest'])->middleware('permission:plant_configuration.manage');
        Route::post('/change-requests/{id}/apply', [PlantConfigurationController::class, 'applyChangeRequest'])->middleware('permission:plant_configuration.manage');
    });

    /*
     * Drivers — Master Data
     * Permissions: drivers.view / drivers.update / drivers.manage (block/TAN — critical)
     */
    Route::prefix('drivers')->group(function () {
        Route::get('', [DriverController::class, 'index'])->middleware('permission:drivers.view');
        Route::get('/{id}', [DriverController::class, 'show'])->middleware('permission:drivers.view');
        Route::get('/{id}/auth-media', [DriverController::class, 'authMedia'])->middleware('permission:drivers.view');
        Route::get('/{id}/plant-visits', [DriverController::class, 'plantVisits'])->middleware('permission:drivers.view');
        Route::get('/{id}/events-audit', [DriverController::class, 'eventsAudit'])->middleware('permission:drivers.view');
        Route::post('/export', [DriverController::class, 'export'])->middleware('permission:reports.export');

        Route::post('', [DriverController::class, 'store'])->middleware('permission:drivers.update');
        Route::put('/{id}', [DriverController::class, 'update'])->middleware('permission:drivers.update');

        // Critical
        Route::post('/{id}/block', [DriverController::class, 'block'])->middleware('permission:drivers.manage');
        Route::post('/{id}/unblock', [DriverController::class, 'unblock'])->middleware('permission:drivers.manage');
        Route::post('/{id}/tan', [DriverController::class, 'createTan'])->middleware('permission:tans.manage');
    });

    /*
     * Loading Control — Operations
     * Permissions: loading_control.view / loading_control.manage
     */
    Route::prefix('loading-control')->group(function () {
        Route::get('/station-view', [LoadingControlController::class, 'stationView'])->middleware('permission:loading_control.view');
        Route::get('/active-loadings', [LoadingControlController::class, 'activeLoadings'])->middleware('permission:loading_control.view');
        Route::get('/loadings/{id}', [LoadingControlController::class, 'show'])->middleware('permission:loading_control.view');
        Route::get('/loadings/{id}/events', [LoadingControlController::class, 'events'])->middleware('permission:loading_control.view');
        Route::get('/loadings/{id}/audit', [LoadingControlController::class, 'audit'])->middleware('permission:loading_control.view');

        Route::post('/loadings/{id}/notes', [LoadingControlController::class, 'addNote'])->middleware('permission:loading_control.manage');
    });

    /*
     * Trailers — Master Data
     * Permissions: trailers.view / trailers.update / trailers.manage
     */
    Route::prefix('trailers')->group(function () {
        Route::get('', [TrailerController::class, 'index'])->middleware('permission:trailers.view');
        Route::get('/{id}', [TrailerController::class, 'show'])->middleware('permission:trailers.view');
        Route::get('/{id}/auth-media', [TrailerController::class, 'authMedia'])->middleware('permission:trailers.view');
        Route::get('/{id}/plant-visits', [TrailerController::class, 'plantVisits'])->middleware('permission:trailers.view');
        Route::get('/{id}/loadings', [TrailerController::class, 'loadings'])->middleware('permission:trailers.view');
        Route::get('/{id}/documents', [TrailerController::class, 'documents'])->middleware('permission:trailers.view');
        Route::get('/{id}/events-audit', [TrailerController::class, 'eventsAudit'])->middleware('permission:trailers.view');
        Route::post('/export', [TrailerController::class, 'export'])->middleware('permission:reports.export');

        Route::post('', [TrailerController::class, 'store'])->middleware('permission:trailers.update');
        Route::put('/{id}', [TrailerController::class, 'update'])->middleware('permission:trailers.update');

        Route::post('/{id}/block', [TrailerController::class, 'block'])->middleware('permission:trailers.manage');
        Route::post('/{id}/unblock', [TrailerController::class, 'unblock'])->middleware('permission:trailers.manage');
    });

    /*
     * Freight Forwarders / Carriers — Master Data
     * Permissions: carriers.view / carriers.update / carriers.manage
     */
    Route::prefix('freight-forwarders-carriers')->group(function () {
        Route::get('', [CarrierController::class, 'index'])->middleware('permission:carriers.view');
        Route::get('/{id}', [CarrierController::class, 'show'])->middleware('permission:carriers.view');
        Route::get('/{id}/drivers', [CarrierController::class, 'drivers'])->middleware('permission:carriers.view');
        Route::get('/{id}/vehicles', [CarrierController::class, 'vehicles'])->middleware('permission:carriers.view');
        Route::get('/{id}/trailers', [CarrierController::class, 'trailers'])->middleware('permission:carriers.view');
        Route::get('/{id}/orders', [CarrierController::class, 'orders'])->middleware('permission:carriers.view');
        Route::get('/{id}/plant-visits', [CarrierController::class, 'plantVisits'])->middleware('permission:carriers.view');
        Route::get('/{id}/events-audit', [CarrierController::class, 'eventsAudit'])->middleware('permission:carriers.view');
        Route::post('/export', [CarrierController::class, 'export'])->middleware('permission:reports.export');

        Route::post('', [CarrierController::class, 'store'])->middleware('permission:carriers.update');
        Route::put('/{id}', [CarrierController::class, 'update'])->middleware('permission:carriers.update');

        Route::post('/{id}/block', [CarrierController::class, 'block'])->middleware('permission:carriers.manage');
        Route::post('/{id}/unblock', [CarrierController::class, 'unblock'])->middleware('permission:carriers.manage');
    });

    /*
     * Tractors / Vehicles — Master Data
     * Permissions: vehicles.view / vehicles.update / vehicles.manage
     */
    Route::prefix('tractors-vehicles')->group(function () {
        Route::get('', [TractorVehicleController::class, 'index'])->middleware('permission:vehicles.view');
        Route::get('/{id}', [TractorVehicleController::class, 'show'])->middleware('permission:vehicles.view');
        Route::get('/{id}/couplings', [TractorVehicleController::class, 'couplings'])->middleware('permission:vehicles.view');
        Route::get('/{id}/plant-visits', [TractorVehicleController::class, 'plantVisits'])->middleware('permission:vehicles.view');
        Route::get('/{id}/clarifications', [TractorVehicleController::class, 'clarifications'])->middleware('permission:vehicles.view');
        Route::get('/{id}/events-audit', [TractorVehicleController::class, 'eventsAudit'])->middleware('permission:vehicles.view');
        Route::post('/export', [TractorVehicleController::class, 'export'])->middleware('permission:reports.export');

        Route::post('', [TractorVehicleController::class, 'store'])->middleware('permission:vehicles.update');
        Route::put('/{id}', [TractorVehicleController::class, 'update'])->middleware('permission:vehicles.update');

        Route::post('/{id}/block', [TractorVehicleController::class, 'block'])->middleware('permission:vehicles.manage');
        Route::post('/{id}/unblock', [TractorVehicleController::class, 'unblock'])->middleware('permission:vehicles.manage');
    });

    /*
     * Master Data Export — Master Data
     * Permission: reports.export (export work crosses all master data)
     */
    Route::prefix('master-data-export')->group(function () {
        Route::get('', [MasterDataExportController::class, 'index'])->middleware('permission:reports.export');
        Route::get('/{id}', [MasterDataExportController::class, 'show'])->middleware('permission:reports.export');
        Route::get('/{id}/download', [MasterDataExportController::class, 'download'])->middleware('permission:reports.export');

        Route::post('', [MasterDataExportController::class, 'store'])->middleware('permission:reports.export');
        Route::post('/{id}/retry', [MasterDataExportController::class, 'retry'])->middleware('permission:reports.export');
    });

    /*
     * Chip Cards — Master Data
     * Permissions: chip_cards.view / chip_cards.manage (all writes — assignment + lifecycle are critical per V1 §10.2)
     */
    Route::prefix('chip-cards')->group(function () {
        Route::get('', [ChipCardController::class, 'index'])->middleware('permission:chip_cards.view');
        Route::get('/{id}', [ChipCardController::class, 'show'])->middleware('permission:chip_cards.view');
        Route::get('/{id}/assignment-history', [ChipCardController::class, 'assignmentHistory'])->middleware('permission:chip_cards.view');
        Route::get('/{id}/usage-history', [ChipCardController::class, 'usageHistory'])->middleware('permission:chip_cards.view');
        Route::get('/{id}/events-audit', [ChipCardController::class, 'eventsAudit'])->middleware('permission:chip_cards.view');
        Route::post('/export', [ChipCardController::class, 'export'])->middleware('permission:reports.export');

        Route::post('', [ChipCardController::class, 'store'])->middleware('permission:chip_cards.manage');
        Route::put('/{id}', [ChipCardController::class, 'update'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/assign', [ChipCardController::class, 'assign'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/unassign', [ChipCardController::class, 'unassign'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/block', [ChipCardController::class, 'block'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/unblock', [ChipCardController::class, 'unblock'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/mark-lost', [ChipCardController::class, 'markLost'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/mark-defective', [ChipCardController::class, 'markDefective'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/replace', [ChipCardController::class, 'replace'])->middleware('permission:chip_cards.manage');
        Route::post('/{id}/archive', [ChipCardController::class, 'archive'])->middleware('permission:chip_cards.manage');
    });

    /*
     * TANs — Master Data
     * Permissions: tans.view / tans.manage (generate + revoke — critical per V1.3 §5.1)
     */
    Route::prefix('tans')->group(function () {
        Route::get('', [TanController::class, 'index'])->middleware('permission:tans.view');
        Route::get('/{id}', [TanController::class, 'show'])->middleware('permission:tans.view');
        Route::get('/{id}/usage-history', [TanController::class, 'usageHistory'])->middleware('permission:tans.view');
        Route::get('/{id}/security-events', [TanController::class, 'securityEvents'])->middleware('permission:tans.view');
        Route::get('/{id}/events-audit', [TanController::class, 'eventsAudit'])->middleware('permission:tans.view');
        Route::post('/export', [TanController::class, 'export'])->middleware('permission:reports.export');

        Route::post('', [TanController::class, 'store'])->middleware('permission:tans.manage');
        Route::post('/{id}/revoke', [TanController::class, 'revoke'])->middleware('permission:tans.manage');
        Route::post('/{id}/expire-now', [TanController::class, 'expireNow'])->middleware('permission:tans.manage');
    });

    /*
     * Clarification Cases — Operations
     * Permissions: clarification_cases.view / clarification_cases.manage
     */
    Route::prefix('clarification-cases')->group(function () {
        Route::get('', [ClarificationCaseController::class, 'index'])->middleware('permission:clarification_cases.view');
        Route::get('/{id}', [ClarificationCaseController::class, 'show'])->middleware('permission:clarification_cases.view');

        Route::post('', [ClarificationCaseController::class, 'store'])->middleware('permission:clarification_cases.manage');
        Route::post('/{id}/acknowledge', [ClarificationCaseController::class, 'acknowledge'])->middleware('permission:clarification_cases.manage');
        Route::post('/{id}/assign', [ClarificationCaseController::class, 'assign'])->middleware('permission:clarification_cases.manage');
        Route::post('/{id}/move-to-waiting-for-owner', [ClarificationCaseController::class, 'moveToWaitingForOwner'])->middleware('permission:clarification_cases.manage');
        Route::post('/{id}/resolve', [ClarificationCaseController::class, 'resolve'])->middleware('permission:clarification_cases.manage');
        Route::post('/{id}/close', [ClarificationCaseController::class, 'close'])->middleware('permission:clarification_cases.manage');
    });

    /*
     * Loading Orders — Orders
     * Permissions: loading_orders.view / .create / .update / .manage (block/cancel/assign)
     */
    Route::prefix('loading-orders')->group(function () {
        Route::get('', [LoadingOrderController::class, 'index'])->middleware('permission:loading_orders.view');
        Route::get('/{id}', [LoadingOrderController::class, 'show'])->middleware('permission:loading_orders.view');
        Route::get('/{id}/events-audit', [LoadingOrderController::class, 'eventsAudit'])->middleware('permission:loading_orders.view');

        Route::post('', [LoadingOrderController::class, 'store'])->middleware('permission:loading_orders.create');
        Route::put('/{id}', [LoadingOrderController::class, 'update'])->middleware('permission:loading_orders.update');

        Route::post('/{id}/assign-driver', [LoadingOrderController::class, 'assignDriver'])->middleware('permission:loading_orders.manage');
        Route::post('/{id}/unassign-driver', [LoadingOrderController::class, 'unassignDriver'])->middleware('permission:loading_orders.manage');
        Route::post('/{id}/assign-trailer', [LoadingOrderController::class, 'assignTrailer'])->middleware('permission:loading_orders.manage');
        Route::post('/{id}/unassign-trailer', [LoadingOrderController::class, 'unassignTrailer'])->middleware('permission:loading_orders.manage');
        Route::post('/{id}/block', [LoadingOrderController::class, 'block'])->middleware('permission:loading_orders.manage');
        Route::post('/{id}/unblock', [LoadingOrderController::class, 'unblock'])->middleware('permission:loading_orders.manage');
        Route::post('/{id}/cancel', [LoadingOrderController::class, 'cancel'])->middleware('permission:loading_orders.manage');
    });

    /*
     * Plant Visits — Operations
     * Permissions: plant_visits.view / plant_visits.manage
     */
    Route::prefix('plant-visits')->group(function () {
        Route::get('', [PlantVisitController::class, 'index'])->middleware('permission:plant_visits.view');
        Route::get('/{id}', [PlantVisitController::class, 'show'])->middleware('permission:plant_visits.view');
        Route::get('/{id}/events-audit', [PlantVisitController::class, 'eventsAudit'])->middleware('permission:plant_visits.view');

        Route::post('', [PlantVisitController::class, 'store'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/advance-step', [PlantVisitController::class, 'advanceStep'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/change-location', [PlantVisitController::class, 'changeLocation'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/wait', [PlantVisitController::class, 'wait'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/resume', [PlantVisitController::class, 'resume'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/block', [PlantVisitController::class, 'block'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/unblock', [PlantVisitController::class, 'unblock'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/raise-clarification', [PlantVisitController::class, 'raiseClarification'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/mark-ready-for-exit', [PlantVisitController::class, 'markReadyForExit'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/close', [PlantVisitController::class, 'close'])->middleware('permission:plant_visits.manage');
        Route::post('/{id}/force-close', [PlantVisitController::class, 'forceClose'])->middleware('permission:plant_visits.manage');
    });

    /*
     * SAP Sync / Order Import Status — read-only per V1.5 §2.2
     * Permission: sap_sync.view
     */
    Route::prefix('sap-sync')->group(function () {
        Route::get('', [\App\Http\Controllers\Api\SapSyncController::class, 'index'])->middleware('permission:sap_sync.view');
        Route::get('/{id}', [\App\Http\Controllers\Api\SapSyncController::class, 'show'])->middleware('permission:sap_sync.view');
    });

    /*
     * Gate & Terminal Monitor — read-only per V2.3 §2.2
     * Permission: gate_terminal.view
     */
    Route::prefix('gate-terminal-monitor')->group(function () {
        Route::get('/touchpoints', [GateTerminalMonitorController::class, 'touchpoints'])->middleware('permission:gate_terminal.view');
        Route::get('/sessions', [GateTerminalMonitorController::class, 'sessions'])->middleware('permission:gate_terminal.view');
        Route::get('/sessions/{id}', [GateTerminalMonitorController::class, 'show'])->middleware('permission:gate_terminal.view');
    });

    /*
     * Operational Documents — Documents & Reports
     * Permissions: operational_documents.view (read) / .print (print) / .manage (reprint/handover/invalidate — critical per V1.2 §13)
     */
    Route::prefix('documents-reports/operational-documents')->group(function () {
        Route::get('', [OperationalDocumentController::class, 'index'])->middleware('permission:operational_documents.view');
        Route::get('/{id}', [OperationalDocumentController::class, 'show'])->middleware('permission:operational_documents.view');
        Route::get('/{id}/preview', [OperationalDocumentController::class, 'preview'])->middleware('permission:operational_documents.view');
        Route::get('/{id}/print-history', [OperationalDocumentController::class, 'printHistory'])->middleware('permission:operational_documents.view');

        Route::post('/{id}/print', [OperationalDocumentController::class, 'print'])->middleware('permission:operational_documents.print');
        Route::post('/{id}/reprint', [OperationalDocumentController::class, 'reprint'])->middleware('permission:operational_documents.manage');
        Route::post('/{id}/hand-over', [OperationalDocumentController::class, 'handOver'])->middleware('permission:operational_documents.manage');
        Route::post('/{id}/invalidate', [OperationalDocumentController::class, 'invalidate'])->middleware('permission:operational_documents.manage');
    });

    /*
     * Reports — Documents & Reports (V2.1)
     * Permissions: reports.view (read) / reports.export (export)
     */
    Route::prefix('documents-reports/reports')->group(function () {
        Route::get('', [ReportsController::class, 'hub'])->middleware('permission:reports.view');
        Route::get('/{reportId}', [ReportsController::class, 'show'])->middleware('permission:reports.view');
        Route::get('/{reportId}/drill-down', [ReportsController::class, 'drillDown'])->middleware('permission:reports.view');
        Route::post('/{reportId}/export', [ReportsController::class, 'export'])->middleware('permission:reports.export');
    });

    /*
     * Products & Quality Specifications (V2.1)
     * Permissions: analysis.view (read) / analysis.manage (write)
     * Two related entities, each with a draft/active/retired lifecycle and
     * row-level edit audit. Edits to ACTIVE rows require a `reason`; the
     * service enforces "all 6 components configured" before activate.
     */
    Route::prefix('product-specifications')->group(function () {
        Route::get('', [ProductSpecificationController::class, 'index'])->middleware('permission:analysis.view');
        Route::get('/{id}', [ProductSpecificationController::class, 'show'])->middleware('permission:analysis.view');

        Route::post('', [ProductSpecificationController::class, 'store'])->middleware('permission:analysis.manage');
        Route::put('/{id}', [ProductSpecificationController::class, 'update'])->middleware('permission:analysis.manage');
        Route::post('/{id}/activate', [ProductSpecificationController::class, 'activate'])->middleware('permission:analysis.manage');
        Route::post('/{id}/retire', [ProductSpecificationController::class, 'retire'])->middleware('permission:analysis.manage');

        // One row per (spec, component) — POST to add, PUT to edit by rowId
        Route::post('/{id}/gas-limits', [ProductSpecificationController::class, 'addGasLimit'])->middleware('permission:analysis.manage');
        Route::put('/{id}/gas-limits/{rowId}', [ProductSpecificationController::class, 'updateGasLimit'])->middleware('permission:analysis.manage');
    });

    Route::prefix('calibration-profiles')->group(function () {
        Route::get('', [CalibrationProfileController::class, 'index'])->middleware('permission:analysis.view');
        Route::get('/{id}', [CalibrationProfileController::class, 'show'])->middleware('permission:analysis.view');

        Route::post('', [CalibrationProfileController::class, 'store'])->middleware('permission:analysis.manage');
        Route::put('/{id}', [CalibrationProfileController::class, 'update'])->middleware('permission:analysis.manage');
        Route::post('/{id}/activate', [CalibrationProfileController::class, 'activate'])->middleware('permission:analysis.manage');
        Route::post('/{id}/retire', [CalibrationProfileController::class, 'retire'])->middleware('permission:analysis.manage');

        Route::post('/{id}/components', [CalibrationProfileController::class, 'addComponent'])->middleware('permission:analysis.manage');
        Route::put('/{id}/components/{rowId}', [CalibrationProfileController::class, 'updateComponent'])->middleware('permission:analysis.manage');
    });

    /*
     * Analysis Devices (V1) — read-only readiness monitor for the 3 MVP
     * analysis devices (OrthoSmart / REGARD3900 / SAM1000DP2).
     * Permissions: analysis.view only — no write surface in MVP per V1 §4.2.
     */
    Route::prefix('analysis-devices')->group(function () {
        Route::get('', [AnalysisDeviceController::class, 'index'])->middleware('permission:analysis.view');
        Route::get('/status-table', [AnalysisDeviceController::class, 'statusTable'])->middleware('permission:analysis.view');
        Route::get('/{id}', [AnalysisDeviceController::class, 'show'])->middleware('permission:analysis.view');
        Route::get('/{id}/channels', [AnalysisDeviceController::class, 'channels'])->middleware('permission:analysis.view');
        Route::get('/{id}/events', [AnalysisDeviceController::class, 'events'])->middleware('permission:analysis.view');
    });

    /*
     * Active Analyses (V1.4) — action-first operational queue + workbench.
     * Permissions: analysis.view (read) / analysis.manage (8 user actions
     * per V1.4 §5 canonical catalogue).
     *
     * The 8 write endpoints map 1:1 to the spec's canonical action list:
     * put on hold / repeat / cancel / release loading (VA-2) / reject
     * loading (VA-4) / open fault case (VA-5+HA-4) / repeat measurement
     * (HA-3) / manual functional approval (HA-5).
     */
    Route::prefix('active-analyses')->group(function () {
        Route::get('', [ActiveAnalysisController::class, 'index'])->middleware('permission:analysis.view');
        Route::get('/{id}', [ActiveAnalysisController::class, 'show'])->middleware('permission:analysis.view');

        Route::post('/{id}/hold', [ActiveAnalysisController::class, 'hold'])->middleware('permission:analysis.manage');
        Route::post('/{id}/repeat', [ActiveAnalysisController::class, 'repeat'])->middleware('permission:analysis.manage');
        Route::post('/{id}/cancel', [ActiveAnalysisController::class, 'cancel'])->middleware('permission:analysis.manage');
        Route::post('/{id}/release-loading', [ActiveAnalysisController::class, 'releaseLoading'])->middleware('permission:analysis.manage');
        Route::post('/{id}/reject-loading', [ActiveAnalysisController::class, 'rejectLoading'])->middleware('permission:analysis.manage');
        Route::post('/{id}/open-fault-case', [ActiveAnalysisController::class, 'openFaultCase'])->middleware('permission:analysis.manage');
        Route::post('/{id}/repeat-measurement', [ActiveAnalysisController::class, 'repeatMeasurement'])->middleware('permission:analysis.manage');
        Route::post('/{id}/manual-approval', [ActiveAnalysisController::class, 'manualApproval'])->middleware('permission:analysis.manage');
    });

    /*
     * Results & Quality Decisions (V1.1) — read-only review surface.
     * Permission: analysis.view (no write endpoints; lifecycle actions
     * for open analyses live in /active-analyses per V1.1 §4).
     *
     * Default list returns only closed/cancelled rows. Pass
     * `include_open_decisions=true` to widen for read-only review;
     * the resource sets `actionOwner=active_analyses` on those rows.
     */
    Route::prefix('results-quality-decisions')->group(function () {
        Route::get('', [QualityDecisionController::class, 'index'])->middleware('permission:analysis.view');
        Route::get('/{id}', [QualityDecisionController::class, 'show'])->middleware('permission:analysis.view');
    });

    /*
     * Interface Health (V1.4 §9) — SAP/PLC/printer/cloud/VPN comm boundary
     * monitor. Read-mostly: list + detail + safe manual retry stub.
     * Permissions: system_devices.view (read) / system_devices.manage (retry).
     */
    Route::prefix('interface-health')->group(function () {
        Route::get('', [InterfaceHealthController::class, 'index'])->middleware('permission:system_devices.view');
        Route::get('/{id}', [InterfaceHealthController::class, 'show'])->middleware('permission:system_devices.view');
        Route::post('/{id}/retry', [InterfaceHealthController::class, 'retry'])->middleware('permission:system_devices.manage');
    });

    /*
     * Hardware Devices (V1.4) — master device registry + Operational
     * Summary Bar + 3 safe writes. Read-mostly per V1.4 §10: no PLC
     * writes, no gate open, no force-release, no ESD reset. The 3
     * allowed writes (service-mode set/restore + non-invasive connection
     * test) are audit-tracked and gated by system_devices.manage.
     *
     * The 4 internal hardware tabs (Terminals & Panels, Printers, Card
     * Readers, PLC / OPC UA Health) soft-FK to hardware_devices.id and
     * ship in later slices.
     */
    Route::prefix('hardware-devices')->group(function () {
        Route::get('', [HardwareDeviceController::class, 'index'])->middleware('permission:system_devices.view');
        Route::get('/{id}', [HardwareDeviceController::class, 'show'])->middleware('permission:system_devices.view');
        Route::get('/{id}/events', [HardwareDeviceController::class, 'events'])->middleware('permission:system_devices.view');

        Route::post('/{id}/service-mode', [HardwareDeviceController::class, 'setServiceMode'])->middleware('permission:system_devices.manage');
        Route::post('/{id}/restore', [HardwareDeviceController::class, 'restoreFromServiceMode'])->middleware('permission:system_devices.manage');
        Route::post('/{id}/connection-test', [HardwareDeviceController::class, 'connectionTest'])->middleware('permission:system_devices.manage');
    });

    /*
     * Terminals & Panels (V1.4 §5) — internal Hardware Devices tab.
     * Read-only composite over hardware_devices + terminal_sessions.
     * Service-mode writes live on the parent /api/hardware-devices
     * registry; session state changes are owned by Gate & Terminal
     * Monitor.
     */
    Route::prefix('terminals-panels')->group(function () {
        Route::get('', [TerminalPanelTabController::class, 'index'])->middleware('permission:system_devices.view');
        Route::get('/{deviceId}', [TerminalPanelTabController::class, 'show'])->middleware('permission:system_devices.view');
    });

    /*
     * Printers (V1.4 §6) — internal Hardware Devices tab.
     * Read-only composite over hardware_devices + document_print_attempts
     * + operational_documents. Service-mode writes live on the parent
     * /api/hardware-devices registry; reprint actions live on
     * /api/documents-reports/operational-documents/{id}/reprint.
     */
    Route::prefix('printers')->group(function () {
        Route::get('', [PrinterTabController::class, 'index'])->middleware('permission:system_devices.view');
        Route::get('/{deviceId}', [PrinterTabController::class, 'show'])->middleware('permission:system_devices.view');
    });

});
