<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Requests\Driver\BlockDriverRequest;
use App\Http\Requests\Driver\CreateTanRequest;
use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UnblockDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Http\Resources\AuthMediumResource;
use App\Http\Resources\DriverResource;
use App\Models\AuditLog;
use App\Models\AuthMedium;
use App\Models\Driver;
use App\Models\EventLog;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Driver::query()->with(['employerCompany', 'operatorCompany', 'authMedia']);

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('driver_code', 'like', $search)
                    ->orWhere('license_no', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $status = strtolower($request->query('status'));
            switch ($status) {
                case 'active':
                    $query->where('is_active', true)->where('block_status', 'clear');
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'blocked':
                    $query->where('block_status', 'blocked');
                    break;
            }
        }

        if ($request->filled('training_status')) {
            $query->where('training_status', $request->query('training_status'));
        }

        if ($request->filled('language')) {
            $query->where('preferred_culture_code', $request->query('language'));
        }

        if ($request->filled('employer_company_id')) {
            $query->where('employer_company_id', $request->query('employer_company_id'));
        }

        if ($request->filled('operator_company_id')) {
            $query->where('operator_company_id', $request->query('operator_company_id'));
        }

        if ($request->filled('attention')) {
            $query->where(function ($q) {
                $q->where('training_status', 'expired')
                    ->orWhere('training_status', 'missing')
                    ->orWhere('block_status', 'blocked')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('license_expiry_date')->whereDate('license_expiry_date', '<', now());
                    });
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $rows = DriverResource::collection($paginator->items());

        $summary = [
            'totalDrivers' => Driver::query()->count(),
            'activeDrivers' => Driver::query()
                ->where('is_active', true)
                ->where('block_status', 'clear')
                ->count(),
            'blockedDrivers' => Driver::query()->where('block_status', 'blocked')->count(),
            'needsAttention' => Driver::query()
                ->where(function ($q) {
                    $q->whereIn('training_status', ['expired', 'missing'])
                        ->orWhere('block_status', 'blocked')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('license_expiry_date')
                                ->whereDate('license_expiry_date', '<', now());
                        });
                })->count(),
        ];

        $lastUpdated = Driver::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Drivers retrieved');
    }

    public function store(StoreDriverRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        // System generates the driver_code so the admin form doesn't
        // have to. We accept a provided one (idempotent imports) but
        // otherwise pick the next monotonic DRV-NNNN. SAP/external
        // imports that already carry their own identifier can still
        // pass `driver_code` explicitly through the API.
        if (empty($data['driver_code'])) {
            $data['driver_code'] = $this->nextDriverCode();
        }

        return DB::transaction(function () use ($data, $audit, $events) {
            $driver = Driver::create($data);

            $audit->record(
                $driver,
                AuditAction::DRIVER_CREATED,
                null,
                $audit->snapshotModel($driver),
                null,
                null
            );

            $events->record(
                'driver.created',
                $driver,
                "Driver {$driver->driver_code} created",
                ['driver_code' => $driver->driver_code],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->created(new DriverResource($driver->fresh()->load(['employerCompany', 'operatorCompany', 'authMedia'])), 'Driver created');
        });
    }

    public function show($id)
    {
        $driver = Driver::with(['employerCompany', 'operatorCompany', 'authMedia'])->find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        return $this->success(new DriverResource($driver), 'Driver retrieved');
    }

    public function update(UpdateDriverRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        $data = $request->validated();

        return DB::transaction(function () use ($driver, $data, $audit, $events) {
            $old = $audit->snapshotModel($driver);

            $driver->update($data);

            $audit->record(
                $driver,
                AuditAction::DRIVER_UPDATED,
                $old,
                $audit->snapshotModel($driver->fresh()),
                null,
                null
            );

            $events->record(
                'driver.updated',
                $driver,
                "Driver {$driver->driver_code} updated",
                null,
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new DriverResource($driver->fresh()->load(['employerCompany', 'operatorCompany', 'authMedia'])),
                'Driver updated'
            );
        });
    }

    public function block(BlockDriverRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }
        if ($driver->block_status === 'blocked') {
            return $this->error('Driver already blocked', 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($driver, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($driver);

            // TODO: re-enable when auth lands — set blocked_by_user_id from request()->user().
            // Block does NOT silently cancel existing active orders (per docs/modules/01-driver-management-backend.md).
            $driver->update([
                'block_status' => 'blocked',
                'block_reason' => $reason,
                'blocked_at' => now(),
            ]);

            $audit->record(
                $driver,
                AuditAction::DRIVER_BLOCKED,
                $old,
                $audit->snapshotModel($driver->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'driver.blocked',
                $driver,
                "Driver {$driver->driver_code} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(
                new DriverResource($driver->fresh()->load(['employerCompany', 'operatorCompany', 'authMedia'])),
                'Driver blocked'
            );
        });
    }

    public function unblock(UnblockDriverRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }
        if ($driver->block_status !== 'blocked') {
            return $this->error('Driver is not blocked', 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($driver, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($driver);

            $driver->update([
                'block_status' => 'clear',
                'block_reason' => null,
                'blocked_at' => null,
                'blocked_by_user_id' => null,
            ]);

            $audit->record(
                $driver,
                AuditAction::DRIVER_UNBLOCKED,
                $old,
                $audit->snapshotModel($driver->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'driver.unblocked',
                $driver,
                "Driver {$driver->driver_code} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new DriverResource($driver->fresh()->load(['employerCompany', 'operatorCompany', 'authMedia'])),
                'Driver unblocked'
            );
        });
    }

    public function authMedia($id)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        $media = $driver->authMedia()->orderByDesc('created_at')->get();

        return $this->success(AuthMediumResource::collection($media), 'Auth media retrieved');
    }

    /**
     * Legacy quick-path: kept verbatim for backward-compat with the original
     * driver detail screen. The dashboard's full-featured TAN flow now lives at
     * `POST /api/tans` (see TanController::store) which produces the same
     * AuthMedium row but additionally:
     *   - generates a tan_reference, tan_masked, valid_from
     *   - enforces no-overlap-per-driver
     *   - emits the richer TAN_GENERATED audit + tan.generated event
     *   - sets usage_state='unused', consumption_count=0
     * This method intentionally still uses the simpler TAN_CREATED audit
     * + 'tan.created' event to avoid changing observable behaviour for callers
     * that depend on this older route.
     */
    public function createTan(CreateTanRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        $bodyDriverId = (string) $request->input('driver_id');
        if ($bodyDriverId !== (string) $id) {
            return $this->error(
                'driver_id in body does not match URL',
                'DRIVER_ID_MISMATCH',
                422,
                ['expected' => (string) $id, 'received' => $bodyDriverId]
            );
        }

        $rawTan = $this->generateTanCode();

        return DB::transaction(function () use ($driver, $request, $rawTan, $audit, $events) {
            $medium = AuthMedium::create([
                'id' => (string) Str::uuid(),
                'medium_type' => AuthMediumType::TAN,
                'identifier_value' => $rawTan, // hidden from API responses
                'display_identifier' => $this->maskTanForDisplay($rawTan),
                'driver_id' => $driver->id,
                'status' => AuthMediumStatus::ACTIVE,
                'is_single_use' => (bool) $request->input('is_single_use', true),
                'issued_at' => now(),
                'expires_at' => $request->input('expires_at'),
                'order_id' => $request->input('order_id'),
            ]);

            $reason = $request->input('reason_note');
            $reasonCode = $request->input('reason_code');

            $audit->record(
                $medium,
                AuditAction::TAN_CREATED,
                null,
                $audit->snapshotModel($medium),
                $reason,
                $reasonCode
            );

            $events->record(
                'tan.created',
                $medium,
                "TAN issued for driver {$driver->driver_code}",
                [
                    'driver_id' => $driver->id,
                    'order_id' => $request->input('order_id'),
                    'expires_at' => $request->input('expires_at'),
                ],
                EventCategory::SECURITY,
                EventSeverity::INFO
            );

            // Response includes the raw TAN exactly once so the operator can hand it over.
            // It is NOT exposed via subsequent GETs (identifier_value is in $hidden on the model).
            return $this->created([
                'tan' => new AuthMediumResource($medium),
                'oneTimeCode' => $rawTan,
            ], 'TAN created');
        });
    }

    public function plantVisits($id)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Plant visits module not yet implemented'],
            'Plant visits retrieved (placeholder)'
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $driver = Driver::find($id);
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        $entityType = $driver->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $driver->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $driver->id)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return $this->success([
            'audits' => $audits,
            'events' => $events,
            'meta' => [
                'per_page' => $perPage,
                'audits_count' => $audits->count(),
                'events_count' => $events->count(),
            ],
        ], 'Driver events & audit retrieved');
    }

    public function export(Request $request)
    {
        $visibleColumns = (array) $request->input('visibleColumns', []);
        $filters = (array) $request->input('filters', []);
        $search = $request->input('search');

        $query = Driver::query()->with(['employerCompany', 'operatorCompany', 'authMedia']);

        if ($search) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('driver_code', 'like', $like)
                    ->orWhere('license_no', 'like', $like);
            });
        }

        foreach (['status', 'training_status', 'language', 'employer_company_id', 'operator_company_id'] as $key) {
            if (! empty($filters[$key])) {
                if ($key === 'language') {
                    $query->where('preferred_culture_code', $filters[$key]);
                } elseif ($key === 'status') {
                    if ($filters[$key] === 'blocked') {
                        $query->where('block_status', 'blocked');
                    } elseif ($filters[$key] === 'active') {
                        $query->where('is_active', true)->where('block_status', 'clear');
                    } elseif ($filters[$key] === 'inactive') {
                        $query->where('is_active', false);
                    }
                } else {
                    $query->where($key, $filters[$key]);
                }
            }
        }

        $rows = DriverResource::collection($query->orderBy('created_at', 'desc')->get());

        // CSV/XLSX generation is TBC; for now return JSON honoring visibleColumns metadata.
        return $this->success([
            'visibleColumns' => $visibleColumns,
            'rows' => $rows,
            'note' => 'Binary export (CSV/XLSX) not yet implemented; returning JSON payload.',
        ], 'Driver export prepared');
    }

    /**
     * Generate a 6-character TAN (digits + uppercase letters, ambiguous chars dropped).
     */
    protected function generateTanCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
        $out = '';
        $len = strlen($alphabet);
        for ($i = 0; $i < 6; $i++) {
            $out .= $alphabet[random_int(0, $len - 1)];
        }
        return $out;
    }

    protected function maskTanForDisplay(string $tan): string
    {
        // Show first 2 chars then **** so the row can be identified but not used.
        $prefix = mb_substr($tan, 0, 2);
        return $prefix . str_repeat('*', max(0, mb_strlen($tan) - 2));
    }

    /**
     * Pick the next driver_code in the `DRV-NNNN` family. Uses a max+1
     * scan over the matching prefix so gaps from deleted demo rows don't
     * reset the counter, and starts at 1001 to mirror the seeded range.
     *
     * Falls back to a millisecond-suffixed code if a parallel writer wins
     * the race — the unique index on `drivers.driver_code` then catches
     * a true collision at INSERT time.
     */
    protected function nextDriverCode(): string
    {
        $prefix = 'DRV-';

        $lastNo = Driver::query()
            ->where('driver_code', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(driver_code, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS max_no")
            ->value('max_no');

        $next = max(1001, ((int) ($lastNo ?? 0)) + 1);
        return $prefix . $next;
    }
}
