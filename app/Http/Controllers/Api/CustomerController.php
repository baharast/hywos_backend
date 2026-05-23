<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Exceptions\SapFieldProtectionException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\BlockCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UnblockCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\Sap\SapFieldGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Customer::query();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('legal_name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('sap_customer_no', 'like', $search)
                    ->orWhere('city', 'like', $search)
                    ->orWhere('country', 'like', $search)
                    ->orWhere('primary_contact_name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('sap_link')) {
            $sap = $request->query('sap_link');
            if ($sap === 'linked') {
                $query->whereNotNull('sap_customer_no');
            } elseif ($sap === 'missing') {
                $query->whereNull('sap_customer_no');
            }
        }

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }

        $summary = [
            'total' => Customer::query()->count(),
            'active' => Customer::query()->where('status', 'active')->count(),
            'blocked' => Customer::query()->where('status', 'blocked')->count(),
            'needsAttention' => Customer::query()
                ->where(function ($q) {
                    $q->whereNull('sap_customer_no')
                        ->orWhereNull('document_requirements')
                        ->orWhere('document_requirements', '[]');
                })
                ->count(),
        ];

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $items = CustomerResource::collection($paginator->items());
        $lastUpdated = Customer::query()->max('updated_at');

        return \App\Services\ApiResponse::list($items, $paginator, $summary, $lastUpdated, 'Customers retrieved');
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $customer = Customer::create($data);

        return $this->created(new CustomerResource($customer), 'Customer created');
    }

    public function show($id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        return $this->success(new CustomerResource($customer), 'Customer retrieved');
    }

    public function update(
        UpdateCustomerRequest $request,
        $id,
        SapFieldGuard $sapGuard,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $payload = $request->validated();

        // Enforce SAP-owned field protection for records flagged is_sap_owned=true.
        // Identical values are allowed (idempotent PUT). Different values are rejected
        // with 423 + audit entry.
        try {
            $sapGuard->assertCanUpdate($customer, $payload, Customer::SAP_OWNED_FIELDS);
        } catch (SapFieldProtectionException $e) {
            $audit->record(
                $customer,
                AuditAction::CUSTOMER_SAP_FIELD_UPDATE_REJECTED,
                $audit->snapshotModel($customer),
                null,
                'Rejected local update on SAP-owned fields',
                null
            );
            $events->record(
                'customer.sap_field_update_rejected',
                $customer,
                "Local update rejected on SAP-owned fields for {$customer->code}",
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

        $customer->update($payload);

        return $this->success(new CustomerResource($customer->fresh()), 'Customer updated');
    }

    public function activate(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update(['is_active' => true]);

        return $this->success(new CustomerResource($customer), 'Customer activated');
    }

    public function deactivate(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update(['is_active' => false]);

        return $this->success(new CustomerResource($customer), 'Customer deactivated');
    }

    public function block(BlockCustomerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }
        if ($customer->status === 'blocked') {
            return $this->error('Customer is already blocked', 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($customer, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($customer);

            // TODO: re-enable when auth lands — set blocked_by_user_id from request()->user().
            // Block does NOT cancel existing orders by design (see customers module spec).
            $customer->update([
                'status' => 'blocked',
                'block_reason' => $reason,
                'blocked_at' => now(),
            ]);

            $audit->record(
                $customer,
                AuditAction::CUSTOMER_BLOCKED,
                $old,
                $audit->snapshotModel($customer->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'customer.blocked',
                $customer,
                "Customer {$customer->name} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new CustomerResource($customer->fresh()), 'Customer blocked');
        });
    }

    public function unblock(UnblockCustomerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }
        if ($customer->status !== 'blocked') {
            return $this->error('Customer is not blocked', 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($customer, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($customer);

            $customer->update([
                'status' => 'active',
                'block_reason' => null,
                'blocked_at' => null,
                'blocked_by_user_id' => null,
            ]);

            $audit->record(
                $customer,
                AuditAction::CUSTOMER_UNBLOCKED,
                $old,
                $audit->snapshotModel($customer->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'customer.unblocked',
                $customer,
                "Customer {$customer->name} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new CustomerResource($customer->fresh()), 'Customer unblocked');
        });
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('delete', Customer::class);

        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update(['is_active' => false]);

        return $this->success(null, 'Customer deactivated');
    }
}
