<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\AuthMediumStatus;
use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\AuthMedium;
use App\Models\ExportJob;
use Illuminate\Database\Eloquent\Builder;

class ChipCardsExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::CHIP_CARDS;
    }

    public function defaultFields(): array
    {
        return [
            'card_code', 'card_type', 'masked_uid',
            'status', 'assignment_state',
            'driver_id', 'trailer_id',
            'issued_at', 'expires_at',
            'last_used_at', 'last_used_source', 'last_usage_result',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'serial_number',
            'replacement_of_card_id', 'replaced_by_card_id', 'replacement_reason',
            'lost_at', 'defective_at', 'archived_at',
            'last_used_context',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $card) {
            yield $this->shape($card, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        // Only export chip cards (driver/trailer chips), not TANs or other media.
        $query = AuthMedium::query()->chipCards();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('status', AuthMediumStatus::ACTIVE);
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(AuthMedium $card, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            // SECURITY: never export raw chip identifier — only the masked UID.
            if (in_array($f, ['identifier_value', 'identifier_hash'], true)) {
                continue;
            }
            $row[$f] = match ($f) {
                'issued_at' => $card->issued_at?->toIso8601String(),
                'expires_at' => $card->expires_at?->toIso8601String(),
                'last_used_at' => $card->last_used_at?->toIso8601String(),
                'lost_at' => $card->lost_at?->toIso8601String(),
                'defective_at' => $card->defective_at?->toIso8601String(),
                'archived_at' => $card->archived_at?->toIso8601String(),
                'created_at' => $card->created_at?->toIso8601String(),
                'updated_at' => $card->updated_at?->toIso8601String(),
                default => $card->{$f} ?? null,
            };
        }
        return $row;
    }
}
