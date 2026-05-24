<?php

namespace App\Http\Resources;

use App\Enums\CarrierApprovalState;
use App\Enums\CarrierStatus;
use App\Models\Driver;
use App\Models\FreightForwarder;
use App\Models\TractorVehicle;
use App\Models\Trailer;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class CarrierResource extends JsonResource
{
    public function toArray($request)
    {
        $status = $this->status ?? CarrierStatus::ACTIVE;
        $approvalState = $this->approval_state ?? CarrierApprovalState::PENDING_REVIEW;
        $linkState = $this->sap_link_state ?? 'missing';

        $isSapOwned = (bool) ($this->is_sap_owned ?? false);

        $id = (string) $this->id;

        return [
            'id' => $this->id,
            'carrierCode' => $this->carrier_code,
            'carrierName' => $this->carrier_name,
            'legalName' => $this->legal_name,
            'sapReference' => $this->sap_reference,
            'externalReference' => $this->external_reference,

            'street' => $this->street,
            'postalCode' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,

            'primaryContactName' => $this->primary_contact_name,
            'contactEmail' => $this->contact_email,
            'contactPhone' => $this->contact_phone,

            'status' => [
                'value' => $status,
                'label' => CarrierStatus::label($status),
                'tone' => CarrierStatus::tone($status),
            ],
            'approvalState' => [
                'value' => $approvalState,
                'label' => CarrierApprovalState::label($approvalState),
                'tone' => CarrierApprovalState::tone($approvalState),
            ],
            'linkState' => [
                'value' => $linkState,
                'label' => $linkState === 'linked' ? 'SAP linked' : 'SAP missing',
                'tone' => $linkState === 'linked' ? 'success' : 'warning',
            ],
            'approvedForLoading' => (bool) $this->approved_for_loading,

            'blockReason' => $this->block_reason,
            'blockedAt' => $this->blocked_at?->toIso8601String(),

            'notes' => $this->notes,
            'hasNotes' => ! empty($this->notes),

            'isSapOwned' => $isSapOwned,
            'sapOwnedFields' => $isSapOwned ? FreightForwarder::SAP_OWNED_FIELDS : [],
            'isActive' => (bool) $this->is_active,

            'companyId' => $this->company_id,

            // Placeholders until orders / plant_visits modules exist.
            'openOrderCount' => 0,
            'activePlantVisitCount' => 0,

            // Real counts against existing master-data tables. Drivers currently
            // FK to companies (employer_company_id), so we treat the same UUID
            // value as a best-effort match. See controller `drivers()` for notes.
            'linkedDriverCount' => $this->safeCount(Driver::class, 'employer_company_id', $id)
                + $this->safeCount(Driver::class, 'operator_company_id', $id),
            'linkedVehicleCount' => $this->safeCount(TractorVehicle::class, 'carrier_id', $id),
            'linkedTrailerCount' => $this->safeCount(Trailer::class, 'carrier_id', $id),

            'lastActivityAt' => null,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Safe count helper — tolerates a missing column or unavailable DB so the
     * resource never crashes when related tables are unmigrated or MySQL is down.
     */
    protected function safeCount(string $modelClass, string $column, string $id): int
    {
        try {
            /** @var \Illuminate\Database\Eloquent\Model $sample */
            $sample = new $modelClass;
            if (! Schema::hasColumn($sample->getTable(), $column)) {
                return 0;
            }
            return (int) $modelClass::query()->where($column, $id)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
