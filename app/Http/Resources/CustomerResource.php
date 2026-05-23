<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        $status = $this->status ?? 'active';
        $tone = match ($status) {
            'active' => 'success',
            'inactive' => 'offline',
            'blocked' => 'danger',
            'archived' => 'neutral',
            default => 'neutral',
        };

        return [
            'id' => $this->id,
            'customerCode' => $this->code,
            'customerName' => $this->name,
            'legalName' => $this->legal_name,
            'sapCustomerNo' => $this->sap_customer_no,
            'externalReference' => $this->external_reference,
            'address' => [
                'street' => $this->street,
                'postalCode' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'primaryContactName' => $this->primary_contact_name,
            'contactEmail' => $this->email,
            'contactPhone' => $this->phone,
            'documentRequirements' => $this->document_requirements ?? [],
            'documentRequirementState' => $this->document_requirement_state,
            'defaultDocumentLanguage' => $this->default_document_language,
            'sapLinkState' => $this->sap_link_state,
            'status' => [
                'value' => $status,
                'label' => ucfirst($status),
                'tone' => $tone,
            ],
            'blockReason' => $this->block_reason,
            'blockedAt' => $this->blocked_at?->toIso8601String(),
            'notes' => $this->notes,
            'isActive' => (bool) $this->is_active,
            'siteId' => $this->site_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
