<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportCategory;
use App\Models\ExportJob;

/**
 * Placeholder exporter for the TANs module, which is not yet implemented in
 * the backend. The exporter exists so the API can accept the `tans` category
 * without erroring, and the service layer can attach a non-fatal warning to
 * the export job explaining that no TAN rows were produced.
 *
 * When the TANs module lands, replace this with a real implementation that
 * exports masked TAN only (never the raw value or hash) plus lifecycle metadata.
 */
class TansExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::TANS;
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function defaultFields(): array
    {
        return [
            'tan_reference', 'tan_masked', 'status', 'usage_state',
            'assigned_driver_id', 'valid_from', 'expires_at',
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
        // No-op: TANs module is not implemented yet. The service layer attaches
        // a "module not implemented" warning to the job. Returning immediately
        // from this generator keeps the CSV file consistent (header only).
        return;
        yield; // unreachable; keeps return type as Generator
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return 0;
    }
}
