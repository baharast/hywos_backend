<?php

namespace App\Services\MasterDataExport;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\ExportFormat;
use App\Enums\ExportJobStatus;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Models\ExportJob;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\MasterDataExport\Exporters\ChipCardsExporter;
use App\Services\MasterDataExport\Exporters\CustomersExporter;
use App\Services\MasterDataExport\Exporters\DriversExporter;
use App\Services\MasterDataExport\Exporters\Exporter;
use App\Services\MasterDataExport\Exporters\FreightForwardersExporter;
use App\Services\MasterDataExport\Exporters\TansExporter;
use App\Services\MasterDataExport\Exporters\TractorVehiclesExporter;
use App\Services\MasterDataExport\Exporters\TrailersExporter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Orchestrates the lifecycle of an export job:
 *   create -> generate (synchronous in dev; queue-ready) -> ready/failed.
 *
 * Generation walks the requested categories, asks each Exporter to stream
 * rows, and writes one CSV per category into a single combined CSV file with
 * section headers. XLSX is accepted at the API boundary but currently falls
 * back to CSV with a warning until a spreadsheet package is installed.
 */
class MasterDataExportService
{
    /** Default lifetime of a ready export file before it is marked expired. */
    public const DEFAULT_TTL_HOURS = 24;

    /** Storage disk used for export files. */
    public const STORAGE_DISK = 'local';

    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events,
    ) {}

    /**
     * Create a new export job in `queued` state. Does NOT run the file
     * generation — callers should invoke generate() after persistence.
     */
    public function create(array $setup): ExportJob
    {
        $correlationId = request()->attributes->get(CorrelationIdMiddleware::ATTRIBUTE);

        $job = ExportJob::create([
            'display_name' => $this->buildDisplayName($setup),
            'categories' => $setup['categories'],
            'record_scope' => $setup['record_scope'],
            'date_from' => $setup['date_from'] ?? null,
            'date_to' => $setup['date_to'] ?? null,
            'status_scope' => $setup['status_scope'],
            'field_set' => $setup['field_set'],
            'format' => $setup['format'],
            'status' => ExportJobStatus::QUEUED,
            'requested_by_user_id' => null,                       // auth disabled in dev
            'requested_by_name' => $setup['requested_by_name'] ?? null,
            'correlation_id' => $correlationId,
        ]);

        $this->audit->record(
            $job,
            AuditAction::EXPORT_JOB_CREATED,
            null,
            ['categories' => $job->categories, 'format' => $job->format],
            null,
            null
        );
        $this->events->record(
            'export.job_created',
            $job,
            "Export job created for: " . implode(', ', $job->categories),
            ['format' => $job->format, 'record_scope' => $job->record_scope],
            EventCategory::OPERATIONS,
            EventSeverity::INFO
        );

        return $job;
    }

    /**
     * Run file generation synchronously. Transitions queued -> generating ->
     * ready/failed. Safe to call from a queued job worker later by simply
     * dispatching ProcessExportJob -> service->generate($job).
     */
    public function generate(ExportJob $job): ExportJob
    {
        $job->update([
            'status' => ExportJobStatus::GENERATING,
            'started_at' => now(),
        ]);

        try {
            $warnings = [];
            $totalRows = 0;

            $relativePath = "exports/{$job->id}.csv";
            $fullPath = Storage::disk(self::STORAGE_DISK)->path($relativePath);

            // Ensure directory exists.
            $dir = dirname($fullPath);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException("Cannot create export directory: {$dir}");
            }

            $handle = fopen($fullPath, 'w');
            if ($handle === false) {
                throw new \RuntimeException("Cannot open export file for writing: {$fullPath}");
            }

            try {
                foreach ($job->categories as $categorySlug) {
                    $exporter = $this->resolveExporter($categorySlug);
                    if ($exporter === null) {
                        $warnings[] = [
                            'category' => $categorySlug,
                            'message' => "Unknown export category: {$categorySlug}",
                        ];
                        continue;
                    }
                    if (! $exporter->isImplemented()) {
                        $warnings[] = [
                            'category' => $categorySlug,
                            'message' => ucfirst(str_replace('_', ' ', $categorySlug))
                                . ' module is not yet implemented; section is empty.',
                        ];
                    }

                    $fields = $exporter->resolveFields($job);

                    // Section header per category (helps readers visually separate sheets in CSV).
                    fputcsv($handle, ["# {$categorySlug}"]);
                    fputcsv($handle, $fields);

                    foreach ($exporter->rows($job, $fields) as $row) {
                        $line = [];
                        foreach ($fields as $f) {
                            $line[] = $row[$f] ?? null;
                        }
                        fputcsv($handle, $line);
                        $totalRows++;
                    }

                    // Blank line separator between categories.
                    fputcsv($handle, []);
                }
            } finally {
                fclose($handle);
            }

            // XLSX requested? Note the fallback explicitly so the consumer
            // knows the file extension lied (we still emit .csv on disk).
            if ($job->format === ExportFormat::XLSX) {
                $warnings[] = [
                    'category' => '_format',
                    'message' => 'XLSX format requested but not yet implemented. CSV file produced instead.',
                ];
            }

            $size = file_exists($fullPath) ? filesize($fullPath) : null;

            $job->update([
                'status' => ExportJobStatus::READY,
                'ready_at' => now(),
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
                'file_path' => $relativePath,
                'file_disk' => self::STORAGE_DISK,
                'file_size_bytes' => $size,
                'record_count_actual' => $totalRows,
                'warnings' => $warnings ?: null,
            ]);

            return $job->fresh();
        } catch (Throwable $e) {
            $job->update([
                'status' => ExportJobStatus::FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            $this->audit->record(
                $job,
                AuditAction::EXPORT_JOB_FAILED,
                null,
                ['error' => $e->getMessage()],
                $e->getMessage(),
                null
            );
            $this->events->record(
                'export.job_failed',
                $job,
                "Export job {$job->id} failed: {$e->getMessage()}",
                ['categories' => $job->categories],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $job->fresh();
        }
    }

    public function recordDownload(ExportJob $job): void
    {
        $this->audit->record(
            $job,
            AuditAction::EXPORT_JOB_DOWNLOADED,
            null,
            ['file_size_bytes' => $job->file_size_bytes],
            null,
            null
        );
        $this->events->record(
            'export.job_downloaded',
            $job,
            "Export {$job->id} downloaded",
            null,
            EventCategory::OPERATIONS,
            EventSeverity::INFO
        );
    }

    public function recordRetry(ExportJob $original, ExportJob $retry): void
    {
        $this->audit->record(
            $retry,
            AuditAction::EXPORT_JOB_RETRIED,
            ['retry_of' => $original->id],
            ['new_job_id' => $retry->id],
            null,
            null
        );
    }

    protected function resolveExporter(string $slug): ?Exporter
    {
        return match ($slug) {
            'drivers' => app(DriversExporter::class),
            'trailers' => app(TrailersExporter::class),
            'tractors_vehicles' => app(TractorVehiclesExporter::class),
            'customers' => app(CustomersExporter::class),
            'freight_forwarders_carriers' => app(FreightForwardersExporter::class),
            'chip_cards' => app(ChipCardsExporter::class),
            'tans' => app(TansExporter::class),
            default => null,
        };
    }

    protected function buildDisplayName(array $setup): string
    {
        $cats = implode(', ', array_map(
            fn($c) => str_replace('_', ' ', $c),
            $setup['categories']
        ));
        $stamp = now()->format('Y-m-d H:i');
        return "Master data export · {$cats} · {$stamp}";
    }
}
