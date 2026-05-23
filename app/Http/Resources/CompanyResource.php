<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = $this->is_active ? 'active' : 'inactive';

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'description' => $this->description,
            'isActive' => (bool) $this->is_active,
            'status' => [
                'value' => $statusValue,
                'label' => ucfirst($statusValue),
                'tone' => $this->is_active ? 'success' : 'offline',
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
