<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportFieldSet;
use App\Enums\ExportRecordScope;
use App\Models\ExportJob;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractExporter implements Exporter
{
    /**
     * Resolve the column key list for the requested field set.
     * @return string[]
     */
    public function resolveFields(ExportJob $job): array
    {
        return $job->field_set === ExportFieldSet::ALL_EXPORTABLE_FIELDS
            ? $this->allFields()
            : $this->defaultFields();
    }

    /**
     * Apply the job's record-scope filter (created/updated date range) to a
     * query. No-op when the scope is ALL_RECORDS.
     */
    protected function applyRecordScope(Builder $query, ExportJob $job): Builder
    {
        if ($job->record_scope !== ExportRecordScope::CREATED_OR_UPDATED_IN_RANGE) {
            return $query;
        }
        $from = $job->date_from;
        $to = $job->date_to;
        if ($from && $to) {
            $query->where(function (Builder $q) use ($from, $to): void {
                $q->whereBetween('created_at', [$from, $to])
                  ->orWhereBetween('updated_at', [$from, $to]);
            });
        } elseif ($from) {
            $query->where(function (Builder $q) use ($from): void {
                $q->where('created_at', '>=', $from)
                  ->orWhere('updated_at', '>=', $from);
            });
        } elseif ($to) {
            $query->where(function (Builder $q) use ($to): void {
                $q->where('created_at', '<=', $to)
                  ->orWhere('updated_at', '<=', $to);
            });
        }
        return $query;
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return null;
    }

    public function isImplemented(): bool
    {
        return true;
    }
}
