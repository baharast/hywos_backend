<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\CarrierApprovalState;
use App\Enums\CarrierStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Exceptions\SapFieldProtectionException;
use App\Http\Requests\Carrier\BlockCarrierRequest;
use App\Http\Requests\Carrier\StoreCarrierRequest;
use App\Http\Requests\Carrier\UnblockCarrierRequest;
use App\Http\Requests\Carrier\UpdateCarrierRequest;
use App\Http\Resources\CarrierResource;
use App\Http\Resources\DriverResource;
use App\Http\Resources\TractorVehicleResource;
use App\Http\Resources\TrailerResource;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\EventLog;
use App\Models\FreightForwarder;
use App\Models\TractorVehicle;
use App\Models\Trailer;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\Sap\SapFieldGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarrierController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = FreightForwarder::query();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('carrier_name', 'like', $search)
                    ->orWhere('carrier_code', 'like', $search)
                    ->orWhere('legal_name', 'like', $search)
                    ->orWhere('sap_reference', 'like', $search)
                    ->orWhere('primary_contact_name', 'like', $search)
                    ->orWhere('contact_email', 'like', $search)
                    ->orWhere('city', 'like', $search)
                    ->orWhere('country', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('approval_state')) {
            $query->where('approval_state', $request->query('approval_state'));
        }

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        if ($request->filled('sap_link')) {
            $sap = $request->query('sap_link');
            if ($sap === 'linked') {
                $query->whereNotNull('sap_reference');
            } elseif ($sap === 'missing') {
                $query->whereNull('sap_reference');
            }
        }

        if ($request->has('approved_for_loading')) {
            $query->where('approved_for_loading', filter_var($request->query('approved_for_loading'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('has_open_orders')) {
            // Loading orders module not yet implemented — always returns no carriers
            // when the caller requires open orders, otherwise no filter.
            if (filter_var($request->query('has_open_orders'), FILTER_VALIDATE_BOOLEAN)) {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Sort
        $sort = $request->query('sort');
        $allowedSorts = [
            'carrier_name', 'carrier_code', 'sap_reference', 'country', 'city',
            'status', 'approval_state', 'created_at', 'updated_at',
        ];
        if ($sort) {
            $direction = 'asc';
            $field = $sort;
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $field = substr($sort, 1);
            }
            if (in_array($field, $allowedSorts, true)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $summary = [
            'totalCarriers' => FreightForwarder::query()->count(),
            'activeCarriers' => FreightForwarder::query()
                ->where('status', CarrierStatus::ACTIVE)
                ->where('approval_state', CarrierApprovalState::APPROVED)
                ->where('approved_for_loading', true)
                ->count(),
            'blockedCarriers' => FreightForwarder::query()
                ->where('status', CarrierStatus::BLOCKED)
                ->count(),
            'needsAttention' => FreightForwarder::query()
                ->where(function ($q) {
                    $q->whereIn('approval_state', [
                        CarrierApprovalState::PENDING_REVIEW,
                        CarrierApprovalState::MISSING_DATA,
                        CarrierApprovalState::NOT_APPROVED,
                    ])
                        ->orWhereNull('sap_reference')
                        ->orWhereNull('contact_email');
                })
                ->count(),
        ];

        $paginator = $query->paginate($perPage);
        $rows = CarrierResource::collection($paginator->items());
        $lastUpdated = FreightForwarder::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Carriers retrieved');
    }

    public function store(StoreCarrierRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        // System generates the carrier_code so the admin form doesn't
        // have to. We accept a provided one (idempotent imports) but
        // otherwise pick the next monotonic CARR-NNNN. SAP-sourced rows
        // can still pass their own code through the API.
        if (empty($data['carrier_code'])) {
            $data['carrier_code'] = $this->nextCarrierCode();
        }

        return DB::transaction(function () use ($data, $audit, $events) {
            $carrier = FreightForwarder::create($data);

            $audit->record(
                $carrier,
                AuditAction::CARRIER_CREATED,
                null,
                $audit->snapshotModel($carrier),
                null,
                null
            );

            $events->record(
                'carrier.created',
                $carrier,
                "Carrier {$carrier->carrier_code} created",
                ['carrier_code' => $carrier->carrier_code],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->created(new CarrierResource($carrier->fresh()), 'Carrier created');
        });
    }

    public function show($id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        return $this->success(new CarrierResource($carrier), 'Carrier retrieved');
    }

    public function update(
        UpdateCarrierRequest $request,
        $id,
        SapFieldGuard $sapGuard,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        $payload = $request->validated();

        try {
            $sapGuard->assertCanUpdate($carrier, $payload, FreightForwarder::SAP_OWNED_FIELDS);
        } catch (SapFieldProtectionException $e) {
            $audit->record(
                $carrier,
                AuditAction::CARRIER_SAP_FIELD_UPDATE_REJECTED,
                $audit->snapshotModel($carrier),
                null,
                'Rejected local update on SAP-owned fields',
                null
            );
            $events->record(
                'carrier.sap_field_update_rejected',
                $carrier,
                "Local update rejected on SAP-owned fields for {$carrier->carrier_code}",
                ['locked_fields' => $e->lockedFields],
                EventCategory::SECURITY,
                EventSeverity::WARNING
            );
            return $this->error(
                $e->getMessage(),
                'SAP_FIELDS_LOCKED',
                423,
                ['lockedFields' => $e->lockedFields]
            );
        }

        return DB::transaction(function () use ($carrier, $payload, $audit, $events) {
            $old = $audit->snapshotModel($carrier);
            $oldApproval = $carrier->approval_state;

            $carrier->update($payload);
            $new = $audit->snapshotModel($carrier->fresh());

            $audit->record($carrier, AuditAction::CARRIER_UPDATED, $old, $new, null, null);

            $events->record(
                'carrier.updated',
                $carrier,
                "Carrier {$carrier->carrier_code} updated",
                null,
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            if ($oldApproval !== $carrier->approval_state) {
                $audit->record(
                    $carrier,
                    AuditAction::CARRIER_APPROVAL_CHANGED,
                    ['approval_state' => $oldApproval],
                    ['approval_state' => $carrier->approval_state],
                    null,
                    null
                );
                $events->record(
                    'carrier.approval_changed',
                    $carrier,
                    "Carrier {$carrier->carrier_code} approval changed: {$oldApproval} → {$carrier->approval_state}",
                    ['from' => $oldApproval, 'to' => $carrier->approval_state],
                    EventCategory::OPERATIONS,
                    EventSeverity::INFO
                );
            }

            return $this->success(new CarrierResource($carrier->fresh()), 'Carrier updated');
        });
    }

    public function block(BlockCarrierRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }
        if ($carrier->status === CarrierStatus::BLOCKED) {
            return $this->error('Carrier is already blocked', 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($carrier, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($carrier);

            // TODO: re-enable when auth lands — set blocked_by_user_id from request()->user().
            // Block does NOT cancel existing orders / plant visits by design.
            $carrier->update([
                'status' => CarrierStatus::BLOCKED,
                'block_reason' => $reason,
                'blocked_at' => now(),
            ]);

            $audit->record(
                $carrier,
                AuditAction::CARRIER_BLOCKED,
                $old,
                $audit->snapshotModel($carrier->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'carrier.blocked',
                $carrier,
                "Carrier {$carrier->carrier_name} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new CarrierResource($carrier->fresh()), 'Carrier blocked');
        });
    }

    public function unblock(UnblockCarrierRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }
        if ($carrier->status !== CarrierStatus::BLOCKED) {
            return $this->error('Carrier is not blocked', 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($carrier, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($carrier);

            $carrier->update([
                'status' => CarrierStatus::ACTIVE,
                'block_reason' => null,
                'blocked_at' => null,
                'blocked_by_user_id' => null,
            ]);

            $audit->record(
                $carrier,
                AuditAction::CARRIER_UNBLOCKED,
                $old,
                $audit->snapshotModel($carrier->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'carrier.unblocked',
                $carrier,
                "Carrier {$carrier->carrier_name} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new CarrierResource($carrier->fresh()), 'Carrier unblocked');
        });
    }

    public function drivers(Request $request, $id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        // Drivers currently FK to `companies.id` via employer_company_id /
        // operator_company_id. The freight_forwarders -> drivers cross-link
        // is a separate follow-up. For now we treat the carrier UUID as a
        // best-effort match against either column — returns [] unless the
        // carrier id also exists in companies. See module spec §2.
        $paginator = Driver::query()
            ->with(['employerCompany', 'operatorCompany', 'authMedia'])
            ->where(function ($q) use ($id) {
                $q->where('employer_company_id', $id)
                    ->orWhere('operator_company_id', $id);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $rows = DriverResource::collection($paginator->items());

        return ApiResponse::list(
            $rows,
            $paginator,
            null,
            null,
            'Carrier drivers retrieved (best-effort match against companies FK; cross-link pending)'
        );
    }

    public function vehicles(Request $request, $id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        $paginator = TractorVehicle::query()
            ->where('carrier_id', $id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::list(
            TractorVehicleResource::collection($paginator->items()),
            $paginator,
            null,
            null,
            'Carrier vehicles retrieved'
        );
    }

    public function trailers(Request $request, $id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        $paginator = Trailer::query()
            ->where('carrier_id', $id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::list(
            TrailerResource::collection($paginator->items()),
            $paginator,
            null,
            null,
            'Carrier trailers retrieved'
        );
    }

    public function orders($id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Loading orders module not yet implemented'],
            'Carrier orders retrieved (placeholder)'
        );
    }

    public function plantVisits($id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Plant visits module not yet implemented'],
            'Carrier plant visits retrieved (placeholder)'
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $carrier = FreightForwarder::find($id);
        if (! $carrier) {
            return $this->error('Carrier not found', 'CARRIER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $entityType = $carrier->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $carrier->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $carrier->id)
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
        ], 'Carrier events & audit retrieved');
    }

    public function export(Request $request)
    {
        $visibleColumns = (array) $request->input('visible_columns', $request->input('visibleColumns', []));
        $filters = (array) $request->input('filters', []);
        $search = $request->input('search');

        $query = FreightForwarder::query();

        if ($search) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('carrier_name', 'like', $like)
                    ->orWhere('carrier_code', 'like', $like)
                    ->orWhere('sap_reference', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('country', 'like', $like);
            });
        }

        foreach (['status', 'approval_state', 'country', 'sap_link', 'approved_for_loading'] as $key) {
            if (! array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'sap_link') {
                if ($value === 'linked') {
                    $query->whereNotNull('sap_reference');
                } elseif ($value === 'missing') {
                    $query->whereNull('sap_reference');
                }
            } elseif ($key === 'approved_for_loading') {
                $query->where('approved_for_loading', filter_var($value, FILTER_VALIDATE_BOOLEAN));
            } else {
                $query->where($key, $value);
            }
        }

        $rows = CarrierResource::collection($query->orderBy('created_at', 'desc')->get());

        return $this->success([
            'visibleColumns' => $visibleColumns,
            'rows' => $rows,
            'note' => 'Binary export (CSV/XLSX) not yet implemented; returning JSON payload.',
        ], 'Carrier export prepared');
    }

    /**
     * Pick the next carrier_code in the `CARR-NNNN` family. Uses a max+1
     * scan over the matching prefix so gaps from deleted demo rows don't
     * reset the counter, and starts at 1001 to mirror the seeded range
     * (CARR-1001..CARR-1005).
     */
    protected function nextCarrierCode(): string
    {
        $prefix = 'CARR-';

        $lastNo = FreightForwarder::query()
            ->where('carrier_code', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(carrier_code, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS max_no")
            ->value('max_no');

        $next = max(1001, ((int) ($lastNo ?? 0)) + 1);
        return $prefix . $next;
    }
}
