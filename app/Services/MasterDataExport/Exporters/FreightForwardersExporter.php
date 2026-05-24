<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\ExportJob;
use App\Models\FreightForwarder;
use Illuminate\Database\Eloquent\Builder;

class FreightForwardersExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::FREIGHT_FORWARDERS_CARRIERS;
    }

    public function defaultFields(): array
    {
        return [
            'carrier_code', 'carrier_name', 'legal_name', 'sap_reference',
            'city', 'country',
            'status', 'approval_state', 'approved_for_loading',
            'is_active', 'is_sap_owned',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'external_reference', 'street', 'postal_code',
            'primary_contact_name', 'contact_email', 'contact_phone',
            'company_id', 'block_reason', 'blocked_at', 'notes',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $c) {
            yield $this->shape($c, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        $query = FreightForwarder::query();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('is_active', true)->where('status', 'active');
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(FreightForwarder $c, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            $row[$f] = match ($f) {
                'blocked_at' => $c->blocked_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
                'is_active' => $c->is_active ? '1' : '0',
                'is_sap_owned' => $c->is_sap_owned ? '1' : '0',
                'approved_for_loading' => $c->approved_for_loading ? '1' : '0',
                default => $c->{$f} ?? null,
            };
        }
        return $row;
    }
}
