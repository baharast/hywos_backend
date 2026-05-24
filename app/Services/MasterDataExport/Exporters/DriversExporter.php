<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\Driver;
use App\Models\ExportJob;
use Illuminate\Database\Eloquent\Builder;

class DriversExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::DRIVERS;
    }

    public function defaultFields(): array
    {
        return [
            'driver_code', 'first_name', 'last_name', 'preferred_culture_code',
            'license_no', 'license_expiry_date', 'training_status', 'block_status',
            'is_active', 'employer_company_id', 'operator_company_id',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'national_id_last4', 'training_valid_until', 'phone', 'email',
            'block_reason', 'blocked_at', 'avatar_file_id', 'notes',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $driver) {
            yield $this->shape($driver, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        $query = Driver::query();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('is_active', true)->where('block_status', '!=', 'blocked');
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(Driver $d, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            // SECURITY: never expose national_id_hash, even via "all fields".
            if ($f === 'national_id_hash') { continue; }
            $row[$f] = match ($f) {
                'license_expiry_date' => $d->license_expiry_date?->toDateString(),
                'training_valid_until' => $d->training_valid_until?->toDateString(),
                'blocked_at' => $d->blocked_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
                'updated_at' => $d->updated_at?->toIso8601String(),
                'is_active' => $d->is_active ? '1' : '0',
                default => $d->{$f} ?? null,
            };
        }
        return $row;
    }
}
