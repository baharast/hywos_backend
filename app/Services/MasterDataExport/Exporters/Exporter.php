<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Models\ExportJob;

/**
 * One Exporter per master-data category.
 *
 * The Service iterates the categories requested by an ExportJob and asks each
 * matching Exporter to produce rows for the CSV.
 *
 * Exporters are responsible for:
 *   - declaring the category slug,
 *   - declaring default and full field sets (CSV header columns),
 *   - applying record-scope and status-scope filters to the query,
 *   - shaping each row safely (sensitive fields must be masked or excluded),
 *   - reporting whether the source module is implemented yet.
 */
interface Exporter
{
    public function categorySlug(): string;

    public function isImplemented(): bool;

    /**
     * Column keys used as CSV header for the default field set.
     * @return string[]
     */
    public function defaultFields(): array;

    /**
     * Column keys used as CSV header for the all-exportable field set.
     * @return string[]
     */
    public function allFields(): array;

    /**
     * Stream rows for the given job and field set. Implementations should yield
     * associative arrays keyed by column name (matching the chosen field set).
     *
     * @return \Generator<int, array<string, scalar|null>>
     */
    public function rows(ExportJob $job, array $fields): \Generator;

    /**
     * Estimated row count for the given job, or null if unknown.
     */
    public function estimateCount(ExportJob $job): ?int;
}
