<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\AuthMediumStatus;
use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\AuthMedium;
use App\Models\ExportJob;
use Illuminate\Database\Eloquent\Builder;

/**
 * Real exporter for TANs. Driven by the TanController-backed module.
 *
 * SECURITY: never emit `identifier_value` or `identifier_hash` — both are in
 * AuthMedium::$hidden and explicitly skipped by `shape()` even if asked for.
 */
class TansExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::TANS;
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function defaultFields(): array
    {
        return [
            'tan_reference', 'tan_masked', 'status', 'usage_state',
            'driver_id', 'valid_from', 'expires_at',
            'consumed_at', 'revoked_at',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'related_plant_visit_id', 'related_order_id',
            'related_terminal_session_id', 'revocation_reason',
            'consumption_count', 'reason',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $tan) {
            yield $this->shape($tan, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        $query = AuthMedium::query()->tans();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('status', AuthMediumStatus::ACTIVE)
                ->whereNull('revoked_at')
                ->whereNull('consumed_at')
                ->where(function (Builder $q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(AuthMedium $tan, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            // SECURITY: never export the raw TAN or its hash, even if requested.
            if (in_array($f, ['identifier_value', 'identifier_hash'], true)) {
                continue;
            }
            // `related_order_id` is logically the same column as auth_media.order_id.
            $key = $f === 'related_order_id' ? 'order_id' : $f;

            $row[$f] = match ($f) {
                'valid_from' => $tan->valid_from?->toIso8601String(),
                'expires_at' => $tan->expires_at?->toIso8601String(),
                'consumed_at' => $tan->consumed_at?->toIso8601String(),
                'revoked_at' => $tan->revoked_at?->toIso8601String(),
                'created_at' => $tan->created_at?->toIso8601String(),
                'updated_at' => $tan->updated_at?->toIso8601String(),
                default => $tan->{$key} ?? null,
            };
        }
        return $row;
    }
}
