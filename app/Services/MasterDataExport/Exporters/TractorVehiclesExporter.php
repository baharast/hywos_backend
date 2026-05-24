<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\ExportJob;
use App\Models\TractorVehicle;
use Illuminate\Database\Eloquent\Builder;

class TractorVehiclesExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::TRACTORS_VEHICLES;
    }

    public function defaultFields(): array
    {
        return [
            'vehicle_code', 'license_plate', 'plate_country', 'vehicle_type',
            'carrier_id', 'owner_name', 'default_driver_id',
            'registration_expiry', 'insurance_expiry',
            'status', 'is_active',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'vin',
            'current_trailer_id', 'current_trailer_label',
            'current_visit_id', 'current_visit_no',
            'last_visit_at', 'last_driver_name',
            'has_open_clarification',
            'block_reason', 'blocked_at', 'notes',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $v) {
            yield $this->shape($v, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        $query = TractorVehicle::query();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('is_active', true)->where('status', 'active');
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(TractorVehicle $v, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            $row[$f] = match ($f) {
                'registration_expiry' => $v->registration_expiry?->toDateString(),
                'insurance_expiry' => $v->insurance_expiry?->toDateString(),
                'last_visit_at' => $v->last_visit_at?->toIso8601String(),
                'blocked_at' => $v->blocked_at?->toIso8601String(),
                'created_at' => $v->created_at?->toIso8601String(),
                'updated_at' => $v->updated_at?->toIso8601String(),
                'is_active' => $v->is_active ? '1' : '0',
                'has_open_clarification' => $v->has_open_clarification ? '1' : '0',
                default => $v->{$f} ?? null,
            };
        }
        return $row;
    }
}
