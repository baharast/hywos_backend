<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-report row in the Reports Hub (`GET /api/documents-reports/reports`).
 *
 * The underlying value is a plain associative array from
 * `App\Services\Reports\ReportRegistry::all()` enriched with `lastRefreshedAt`
 * by `ReportsService::hub()` — there's no Eloquent model behind this.
 */
class ReportHubItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            'title' => $this['title'],
            'category' => $this['category'],
            'purpose' => $this['purpose'],
            'primaryUsers' => $this['primaryUsers'] ?? [],
            'defaultOutput' => $this['defaultOutput'] ?? [],
            'availability' => $this['availability'],
            'dataSourceAvailable' => (bool) ($this['dataSourceAvailable'] ?? true),
            'placeholderReason' => $this['placeholderReason'] ?? null,
            'lastRefreshedAt' => $this['lastRefreshedAt'] ?? null,
            'routePath' => "/documents-reports/reports/{$this['id']}",
        ];
    }
}
