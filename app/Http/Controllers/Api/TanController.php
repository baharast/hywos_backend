<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\TanStatus;
use App\Enums\TanUsageState;
use App\Http\Requests\Tan\ExpireTanRequest;
use App\Http\Requests\Tan\GenerateTanRequest;
use App\Http\Requests\Tan\RevokeTanRequest;
use App\Http\Resources\TanResource;
use App\Http\Resources\TanUsageEventResource;
use App\Models\AuditLog;
use App\Models\AuthMedium;
use App\Models\Driver;
use App\Models\EventLog;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\Tans\TanPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TanController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = AuthMedium::query()->tans()->with('driver');
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['tan_reference', 'created_at', 'updated_at', 'expires_at', 'consumed_at', 'valid_from', 'usage_state'];
        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = TanResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = AuthMedium::query()->tans()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'TANs retrieved');
    }

    public function store(GenerateTanRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $driver = Driver::find($request->input('driver_id'));
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }

        // No-overlap policy: a driver may not have two simultaneously-active TANs.
        $overlap = AuthMedium::query()->tans()
            ->forDriver($driver->id)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
        if ($overlap) {
            return $this->error(
                'Driver already has an active TAN',
                'OVERLAPPING_ACTIVE_TAN',
                409,
                ['existingTanId' => $overlap->id]
            );
        }

        return DB::transaction(function () use ($driver, $request, $audit, $events) {
            // Cryptographically-strong 6-digit value via random_int (CSPRNG-backed).
            $min = (int) str_pad('1', TanPolicy::VALUE_DIGITS, '0');
            $max = (int) str_repeat('9', TanPolicy::VALUE_DIGITS);
            $rawValue = (string) random_int($min, $max);
            $masked = '••' . substr($rawValue, -4);

            $reference = $this->nextTanReference();

            $tan = AuthMedium::create([
                'medium_type' => AuthMediumType::TAN,
                'driver_id' => $driver->id,
                'is_single_use' => true,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'consumption_count' => 0,
                'tan_reference' => $reference,
                'tan_masked' => $masked,
                'identifier_hash' => hash('sha256', $rawValue),
                'display_identifier' => $masked,
                'issued_at' => now(),
                'valid_from' => $request->input('valid_from') ?: now(),
                'expires_at' => $request->input('expires_at'),
                'reason' => $request->input('reason'),
                'related_plant_visit_id' => $request->input('related_plant_visit_id'),
                'related_terminal_session_id' => $request->input('related_terminal_session_id'),
                'order_id' => $request->input('related_order_id'),
            ]);

            $audit->record(
                $tan,
                AuditAction::TAN_GENERATED,
                null,
                $audit->snapshotModel($tan),
                $request->input('reason'),
                $request->input('reason_code')
            );

            $events->record(
                'tan.generated',
                $tan,
                "TAN {$reference} generated for {$driver->first_name} {$driver->last_name}",
                ['driver_id' => $driver->id, 'reference' => $reference],
                EventCategory::SECURITY,
                EventSeverity::INFO
            );

            $data = (new TanResource($tan->fresh()->load('driver')))->toArray(request());
            // ONE-TIME REVEAL — the only place the raw TAN ever leaves the backend.
            // Subsequent show()/index() never include this field.
            $data['oneTimeFullValue'] = $rawValue;

            return ApiResponse::success($data, 'TAN generated', 201);
        });
    }

    public function show($id)
    {
        $tan = AuthMedium::query()->tans()->with('driver')->find($id);
        if (! $tan) {
            return $this->error('TAN not found', 'TAN_NOT_FOUND', 404);
        }

        return $this->success(new TanResource($tan), 'TAN retrieved');
    }

    public function revoke(RevokeTanRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $tan = AuthMedium::query()->tans()->find($id);
        if (! $tan) {
            return $this->error('TAN not found', 'TAN_NOT_FOUND', 404);
        }
        if (! is_null($tan->revoked_at)) {
            return $this->error('TAN already revoked', 'ALREADY_REVOKED', 409);
        }
        if (! is_null($tan->consumed_at)) {
            return $this->error('Consumed TANs cannot be revoked', 'NOT_REVOKABLE', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($tan, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($tan);

            // We set status=BLOCKED so the existing AuthMediumStatus enum carries the
            // semantic. The TAN-specific "revoked" state is derived in TanResource via
            // `revoked_at IS NOT NULL` (see TanResource::deriveStatus).
            $tan->update([
                'revoked_at' => now(),
                'revocation_reason' => $reason,
                'status' => AuthMediumStatus::BLOCKED,
                'usage_state' => TanUsageState::BLOCKED,
            ]);

            $audit->record(
                $tan,
                AuditAction::TAN_REVOKED,
                $old,
                $audit->snapshotModel($tan->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'tan.revoked',
                $tan,
                "TAN {$tan->tan_reference} revoked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::SECURITY,
                EventSeverity::WARNING
            );

            return $this->success(new TanResource($tan->fresh()->load('driver')), 'TAN revoked');
        });
    }

    public function expireNow(ExpireTanRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $tan = AuthMedium::query()->tans()->find($id);
        if (! $tan) {
            return $this->error('TAN not found', 'TAN_NOT_FOUND', 404);
        }
        if ($tan->status === AuthMediumStatus::EXPIRED
            || ($tan->expires_at && $tan->expires_at->isPast())
        ) {
            return $this->error('TAN already expired', 'ALREADY_EXPIRED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($tan, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($tan);

            $tan->update([
                'expires_at' => now(),
                'status' => AuthMediumStatus::EXPIRED,
            ]);

            $audit->record(
                $tan,
                AuditAction::TAN_EXPIRED,
                $old,
                $audit->snapshotModel($tan->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'tan.expired',
                $tan,
                "TAN {$tan->tan_reference} expired manually",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::SECURITY,
                EventSeverity::INFO
            );

            return $this->success(new TanResource($tan->fresh()->load('driver')), 'TAN expired');
        });
    }

    public function usageHistory(Request $request, $id)
    {
        $tan = AuthMedium::query()->tans()->find($id);
        if (! $tan) {
            return $this->error('TAN not found', 'TAN_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $paginator = EventLog::query()
            ->where('entity_type', $tan->getMorphClass())
            ->where('entity_id', (string) $tan->id)
            ->where(function ($q) {
                $q->where('event_type', 'like', 'tan.usage%')
                    ->orWhere('event_type', 'tan.consumed')
                    ->orWhere('event_type', 'tan.usage_failed');
            })
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        return ApiResponse::list(
            TanUsageEventResource::collection($paginator->items()),
            $paginator,
            null,
            null,
            'TAN usage history retrieved'
        );
    }

    public function securityEvents($id)
    {
        $tan = AuthMedium::query()->tans()->find($id);
        if (! $tan) {
            return $this->error('TAN not found', 'TAN_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Security events module not yet implemented'],
            'Security events retrieved (placeholder)'
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $tan = AuthMedium::query()->tans()->find($id);
        if (! $tan) {
            return $this->error('TAN not found', 'TAN_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $entityType = $tan->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $tan->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $tan->id)
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
        ], 'TAN events & audit retrieved');
    }

    public function export(Request $request)
    {
        $visibleColumns = (array) $request->input('visible_columns', []);
        $filters = (array) $request->input('filters', []);
        $search = $request->input('search');

        $query = AuthMedium::query()->tans()->with('driver');
        $this->applyFilters($query, array_merge(['search' => $search], $filters));

        $rows = TanResource::collection($query->orderByDesc('created_at')->get());

        return $this->success([
            'visibleColumns' => $visibleColumns,
            'rows' => $rows,
            'note' => 'Binary export (CSV/XLSX) for the per-table button not yet implemented; returning JSON payload. For background CSV/XLSX export jobs use the Master Data Export endpoint.',
        ], 'TAN export prepared');
    }

    // ---------- helpers ----------

    protected function nextTanReference(): string
    {
        $year = now()->format('Y');
        $countThisYear = AuthMedium::query()->tans()
            ->whereYear('created_at', now()->year)
            ->count();
        $seq = str_pad((string) ($countThisYear + 1), 4, '0', STR_PAD_LEFT);
        return "TAN-{$year}-{$seq}";
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('tan_reference', 'like', $search)
                    ->orWhere('tan_masked', 'like', $search)
                    ->orWhere('reason', 'like', $search)
                    ->orWhereHas('driver', function ($d) use ($search) {
                        $d->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('driver_code', 'like', $search);
                    });
            });
        }

        if (! empty($filters['assigned_driver_id'])) {
            $query->where('driver_id', $filters['assigned_driver_id']);
        }

        if (! empty($filters['usage_state'])) {
            $query->where('usage_state', $filters['usage_state']);
        }

        if (! empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        if (! empty($filters['expiry_state'])) {
            $this->applyExpiryStateFilter($query, $filters['expiry_state']);
        }

        if (array_key_exists('has_failed_use', $filters)) {
            $hasFailed = filter_var($filters['has_failed_use'], FILTER_VALIDATE_BOOLEAN);
            if ($hasFailed) {
                $query->where('usage_state', TanUsageState::FAILED);
            }
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }
    }

    protected function applyStatusFilter($query, string $status): void
    {
        // Translate derived TanStatus values to query predicates on the underlying columns.
        switch ($status) {
            case TanStatus::BLOCKED:
                $query->where('status', AuthMediumStatus::BLOCKED)->whereNull('revoked_at');
                break;
            case TanStatus::REVOKED:
                $query->whereNotNull('revoked_at');
                break;
            case TanStatus::CONSUMED:
                $query->whereNotNull('consumed_at');
                break;
            case TanStatus::EXPIRED:
                $query->where(function ($q) {
                    $q->where('status', AuthMediumStatus::EXPIRED)
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('expires_at')->where('expires_at', '<', now());
                        });
                })->whereNull('consumed_at')->whereNull('revoked_at');
                break;
            case TanStatus::PENDING:
                $query->where('status', AuthMediumStatus::ACTIVE)
                    ->whereNull('consumed_at')->whereNull('revoked_at')
                    ->whereNotNull('valid_from')->where('valid_from', '>', now());
                break;
            case TanStatus::ACTIVE:
                $query->where('status', AuthMediumStatus::ACTIVE)
                    ->whereNull('consumed_at')->whereNull('revoked_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
                    });
                break;
        }
    }

    protected function applyExpiryStateFilter($query, string $state): void
    {
        $now = now();
        $soon = now()->addHours(TanPolicy::EXPIRING_SOON_HOURS);
        switch ($state) {
            case 'expired':
                $query->whereNotNull('expires_at')->where('expires_at', '<', $now);
                break;
            case 'expiring_soon':
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '>=', $now)
                    ->where('expires_at', '<=', $soon);
                break;
            case 'valid':
                $query->where(function ($q) use ($soon) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', $soon);
                });
                break;
        }
    }

    protected function summary(): array
    {
        $base = AuthMedium::query()->tans();

        $total = (clone $base)->count();

        $active = (clone $base)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->where('usage_state', TanUsageState::UNUSED)
            ->whereNull('revoked_at')->whereNull('consumed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->count();

        $expiringSoon = (clone $base)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addHours(TanPolicy::EXPIRING_SOON_HOURS))
            ->whereNull('revoked_at')->whereNull('consumed_at')
            ->count();

        $revokedOrConsumedOrFailed = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('revoked_at')
                    ->orWhereNotNull('consumed_at')
                    ->orWhere('usage_state', TanUsageState::FAILED)
                    ->orWhere('usage_state', TanUsageState::BLOCKED);
            })
            ->count();

        return [
            'totalTans' => $total,
            'activeTans' => $active,
            'expiringSoon' => $expiringSoon,
            'revokedOrConsumedOrFailed' => $revokedOrConsumedOrFailed,
        ];
    }
}
