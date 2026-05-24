<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\ExportJob;
use App\Models\Trailer;
use Illuminate\Database\Eloquent\Builder;

class TrailersExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::TRAILERS;
    }

    public function defaultFields(): array
    {
        return [
            'trailer_code', 'trailer_label', 'plate', 'trailer_type',
            'pressure_class', 'volume', 'volume_unit',
            'inspection_expiry_date', 'technical_suitability', 'chip_state',
            'status', 'is_active', 'carrier_id', 'customer_id',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'approved_product_quality', 'inspection_reference',
            'current_parking_id', 'current_context', 'last_visit_at',
            'block_reason', 'blocked_at', 'notes',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $trailer) {
            yield $this->shape($trailer, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        $query = Trailer::query();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('is_active', true)->where('status', 'active');
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(Trailer $t, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            $row[$f] = match ($f) {
                'inspection_expiry_date' => $t->inspection_expiry_date?->toDateString(),
                'last_visit_at' => $t->last_visit_at?->toIso8601String(),
                'blocked_at' => $t->blocked_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
                'updated_at' => $t->updated_at?->toIso8601String(),
                'is_active' => $t->is_active ? '1' : '0',
                'volume' => $t->volume !== null ? (string) $t->volume : null,
                'approved_product_quality' => is_array($t->approved_product_quality)
                    ? implode('|', $t->approved_product_quality)
                    : null,
                default => $t->{$f} ?? null,
            };
        }
        return $row;
    }
}
