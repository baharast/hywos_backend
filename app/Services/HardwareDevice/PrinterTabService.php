<?php

namespace App\Services\HardwareDevice;

use App\Enums\DocumentPrintStatus;
use App\Enums\DocumentType;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Enums\PrinterQueueState;
use App\Models\DocumentPrintAttempt;
use App\Models\HardwareDevice;
use App\Models\OperationalDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Source-facing read for V1.4 §6 Printers internal tab.
 *
 * Composite read over:
 *   - `hardware_devices` (Track A registry — printer health + service mode)
 *   - `document_print_attempts` (Operational Documents V1.2 — print queue history)
 *   - `operational_documents` (resolved per impacted job for exit-blocking signal)
 *
 * No tables added. The link is a strict FK: `document_print_attempts.printer_id`
 * → `hardware_devices.id`. Printers without any attempts still appear (idle
 * state). Per V1.4 §10 this tab is read-only; service-mode writes live on
 * the parent registry (/api/hardware-devices) and reprint actions live on
 * /api/documents-reports/operational-documents/{id}/reprint.
 */
class PrinterTabService
{
    public function __construct(
        protected HardwareDeviceService $hardwareDevices,
    ) {}

    /* ============================================================
     * List query
     * ============================================================ */

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HardwareDevice::query()
            ->where('device_type', HardwareDeviceType::PRINTER);

        $this->applyFilters($query, $filters);

        if (empty($filters['sort'])) {
            $query->orderByRaw($this->hardwareDevices->defaultSortExpression());
        } else {
            $sort = (string) $filters['sort'];
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            $allowed = ['last_event_at', 'last_seen_at', 'asset_tag', 'health'];
            if (! in_array($column, $allowed, true)) {
                $column = 'last_event_at';
                $direction = 'desc';
            }
            $query->orderBy($column, $direction);
        }

        return $query->paginate($perPage);
    }

    /* ============================================================
     * Row enrichment
     * ============================================================ */

    /**
     * Compose the response shape for one printer + its current job snapshot.
     *
     * @return array<string,mixed>
     */
    public function enrichRow(HardwareDevice $device): array
    {
        $counts = $this->jobCountsFor($device);
        $impactedJob = $this->latestImpactedJob($device);
        $lastSuccess = $this->lastSuccessfulAttempt($device);
        $lastError = $this->lastFailedAttempt($device);

        $queueState = PrinterQueueState::derive(
            (int) $counts['printing'],
            (int) $counts['queued'],
            (int) $counts['failed'],
        );

        return [
            'queueState' => [
                'value' => $queueState,
                'label' => PrinterQueueState::label($queueState),
                'tone' => PrinterQueueState::tone($queueState),
            ],
            'jobCounts' => [
                'queued' => (int) $counts['queued'],
                'printing' => (int) $counts['printing'],
                'failed' => (int) $counts['failed'],
                'failedToday' => (int) $counts['failedToday'],
            ],
            'impactedJob' => $impactedJob,
            'exitImpact' => $this->resolveExitImpact($device, $impactedJob),
            'lastSuccessAt' => $lastSuccess?->completed_at?->toIso8601String(),
            'lastError' => $lastError === null ? null : [
                'reason' => $lastError->failure_reason,
                'at' => $lastError->completed_at?->toIso8601String(),
                'attemptNo' => (int) $lastError->attempt_no,
            ],
            'deviceImpact' => $this->resolveDeviceImpact($device, $queueState, $impactedJob),
        ];
    }

    /**
     * Count attempts by status for this printer.
     *
     * "failed" here means the LATEST attempt for a document is `failed`
     * (i.e. not yet superseded by a successful reprint). We approximate
     * that with: failed rows where there is no later attempt on the same
     * document with status in {printed, printing}. SQL keeps it tight
     * via a NOT EXISTS subquery.
     *
     * @return array{queued:int,printing:int,failed:int,failedToday:int}
     */
    public function jobCountsFor(HardwareDevice $device): array
    {
        $queued = DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->where('status', DocumentPrintStatus::QUEUED)
            ->count();

        $printing = DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->where('status', DocumentPrintStatus::PRINTING)
            ->count();

        $failed = DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->where('status', DocumentPrintStatus::FAILED)
            ->whereNotExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('document_print_attempts as a2')
                    ->whereColumn('a2.document_id', 'document_print_attempts.document_id')
                    ->whereColumn('a2.attempt_no', '>', 'document_print_attempts.attempt_no')
                    ->whereIn('a2.status', [
                        DocumentPrintStatus::PRINTED,
                        DocumentPrintStatus::PRINTING,
                    ]);
            })
            ->count();

        $failedToday = DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->where('status', DocumentPrintStatus::FAILED)
            ->whereDate('completed_at', now()->toDateString())
            ->count();

        return [
            'queued' => $queued,
            'printing' => $printing,
            'failed' => $failed,
            'failedToday' => $failedToday,
        ];
    }

    /**
     * Latest in-flight or failed job for this printer — the one the FE
     * column "Document / Impacted Job" displays.
     *
     * Picks: any failed > any printing > any queued > most-recent printed,
     * tied broken by attempt completed_at DESC (then requested_at).
     */
    public function latestImpactedJob(HardwareDevice $device): ?array
    {
        $attempt = DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->whereIn('status', [
                DocumentPrintStatus::FAILED,
                DocumentPrintStatus::PRINTING,
                DocumentPrintStatus::QUEUED,
            ])
            ->orderByRaw("CASE status
                WHEN 'failed' THEN 1
                WHEN 'printing' THEN 2
                WHEN 'queued' THEN 3
                ELSE 9
            END")
            ->orderByDesc('requested_at')
            ->first();

        if ($attempt === null) {
            return null;
        }

        $doc = OperationalDocument::query()->find($attempt->document_id);

        return [
            'attemptId' => $attempt->id,
            'attemptNo' => (int) $attempt->attempt_no,
            'status' => [
                'value' => $attempt->status,
                'label' => DocumentPrintStatus::label($attempt->status),
                'tone' => DocumentPrintStatus::tone($attempt->status),
            ],
            'documentId' => $attempt->document_id,
            'documentNo' => $doc?->document_no,
            'documentType' => $doc === null ? null : [
                'value' => $doc->document_type,
                'label' => DocumentType::label($doc->document_type),
            ],
            'isExitBlocking' => (bool) ($doc?->is_exit_blocking ?? false),
            'orderId' => $doc?->order_id,
            'orderNo' => $doc?->order_no,
            'visitId' => $doc?->plant_visit_id,
            'visitNo' => $doc?->visit_no,
            'requestedAt' => $attempt->requested_at?->toIso8601String(),
            'failureReason' => $attempt->status === DocumentPrintStatus::FAILED
                ? $attempt->failure_reason
                : null,
        ];
    }

    public function lastSuccessfulAttempt(HardwareDevice $device): ?DocumentPrintAttempt
    {
        return DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->where('status', DocumentPrintStatus::PRINTED)
            ->orderByDesc('completed_at')
            ->first();
    }

    public function lastFailedAttempt(HardwareDevice $device): ?DocumentPrintAttempt
    {
        return DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->where('status', DocumentPrintStatus::FAILED)
            ->orderByDesc('completed_at')
            ->first();
    }

    /**
     * V1.4 §6 "Exit Impact" column: a typed signal the FE can render as
     * a badge. Worst-case across the current impacted job + the printer's
     * physical role (driver terminal printer = exit-side).
     *
     * @return array{value:string,label:string,tone:string}
     */
    public function resolveExitImpact(HardwareDevice $device, ?array $impactedJob): array
    {
        if ($impactedJob !== null && $impactedJob['isExitBlocking']) {
            $isFailedOrInflight = in_array($impactedJob['status']['value'], [
                DocumentPrintStatus::FAILED,
                DocumentPrintStatus::PRINTING,
                DocumentPrintStatus::QUEUED,
            ], true);

            if ($isFailedOrInflight) {
                return [
                    'value' => 'blocks_exit',
                    'label' => 'Blocks exit',
                    'tone' => 'danger',
                ];
            }
        }

        if (HardwareDeviceHealth::isAbnormal($device->health ?? '')
            && $device->physical_location === HardwarePhysicalLocation::DRIVER_TERMINAL
        ) {
            return [
                'value' => 'at_risk',
                'label' => 'At risk if mandatory document is required',
                'tone' => 'warning',
            ];
        }

        return [
            'value' => 'no_impact',
            'label' => 'No exit impact',
            'tone' => 'neutral',
        ];
    }

    /**
     * Compose the "Device Impact / Action Needed" cell (V1.4 §5 wording —
     * §6 inherits the same idea).
     */
    public function resolveDeviceImpact(HardwareDevice $device, string $queueState, ?array $impactedJob): array
    {
        if ($device->service_mode) {
            return [
                'severity' => 'info',
                'label' => 'Printer in service mode',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        if (HardwareDeviceHealth::isAbnormal($device->health ?? '')) {
            return [
                'severity' => 'danger',
                'label' => 'Printer fault — operator review required',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        if ($queueState === PrinterQueueState::FAILURES && $impactedJob !== null) {
            return [
                'severity' => 'danger',
                'label' => 'Failed print job — investigate / retry via operational documents',
                'action' => 'Open document detail',
                'route' => "/documents-reports/operational-documents/{$impactedJob['documentId']}",
            ];
        }

        if ($queueState === PrinterQueueState::PRINTING) {
            return [
                'severity' => 'info',
                'label' => 'Printer busy — printing job',
                'action' => null,
                'route' => null,
            ];
        }

        if ($queueState === PrinterQueueState::QUEUED) {
            return [
                'severity' => 'info',
                'label' => 'Print job queued',
                'action' => null,
                'route' => null,
            ];
        }

        return [
            'severity' => 'neutral',
            'label' => 'No active print jobs — printer healthy',
            'action' => null,
            'route' => null,
        ];
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * V1.4 §6 summary block.
     *
     * @return array{
     *   totalPrinters:int, abnormalPrinters:int,
     *   queuedJobs:int, failedJobs:int, exitBlockingJobs:int,
     *   inServiceMode:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $base = HardwareDevice::query()->where('device_type', HardwareDeviceType::PRINTER);

        $operational = (clone $base)->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        $totalPrinters = (clone $operational)->count();

        $abnormalPrinters = (clone $operational)
            ->whereIn('health', [
                HardwareDeviceHealth::WARNING,
                HardwareDeviceHealth::ALARM,
                HardwareDeviceHealth::FAULT,
                HardwareDeviceHealth::OFFLINE,
            ])
            ->count();

        $inServiceMode = (clone $operational)->where('service_mode', true)->count();

        $printerIds = (clone $operational)->pluck('id')->all();

        $queuedJobs = empty($printerIds) ? 0 : DocumentPrintAttempt::query()
            ->whereIn('printer_id', $printerIds)
            ->whereIn('status', [DocumentPrintStatus::QUEUED, DocumentPrintStatus::PRINTING])
            ->count();

        $failedJobs = empty($printerIds) ? 0 : DocumentPrintAttempt::query()
            ->whereIn('printer_id', $printerIds)
            ->where('status', DocumentPrintStatus::FAILED)
            ->whereNotExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('document_print_attempts as a2')
                    ->whereColumn('a2.document_id', 'document_print_attempts.document_id')
                    ->whereColumn('a2.attempt_no', '>', 'document_print_attempts.attempt_no')
                    ->whereIn('a2.status', [
                        DocumentPrintStatus::PRINTED,
                        DocumentPrintStatus::PRINTING,
                    ]);
            })
            ->count();

        // Count exit-blocking documents whose latest open attempt sits on
        // one of our printers.
        $exitBlockingJobs = empty($printerIds) ? 0 : OperationalDocument::query()
            ->where('is_exit_blocking', true)
            ->whereIn('printer_id', $printerIds)
            ->whereIn('print_status', [
                DocumentPrintStatus::QUEUED,
                DocumentPrintStatus::PRINTING,
                DocumentPrintStatus::FAILED,
            ])
            ->count();

        return [
            'totalPrinters' => $totalPrinters,
            'abnormalPrinters' => $abnormalPrinters,
            'queuedJobs' => $queuedJobs,
            'failedJobs' => $failedJobs,
            'exitBlockingJobs' => $exitBlockingJobs,
            'inServiceMode' => $inServiceMode,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    /**
     * V1.4 §3 + §11 rule: dropdown options come from the current tab
     * dataset only (TBC rows excluded).
     */
    public function availableFilterValues(): array
    {
        $base = HardwareDevice::query()
            ->where('device_type', HardwareDeviceType::PRINTER)
            ->where(function (Builder $q) {
                $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
            });

        $rows = (clone $base)->get(['physical_location', 'health']);

        $healths = $rows->pluck('health')->filter()->unique()->values()->all();
        $locations = $rows->pluck('physical_location')->filter()->unique()->values()->all();

        // Document types currently associated with this printer's open
        // attempts — derived from operational_documents.
        $printerIds = (clone $base)->pluck('id')->all();
        $documentTypes = empty($printerIds) ? [] : OperationalDocument::query()
            ->whereIn('printer_id', $printerIds)
            ->pluck('document_type')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'healths' => $healths,
            'locations' => $locations,
            'documentTypes' => $documentTypes,
        ];
    }

    /* ============================================================
     * Filter plumbing
     * ============================================================ */

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('asset_tag', 'like', $search)
                    ->orWhere('vendor_tag', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhere('last_message', 'like', $search)
                    ->orWhere('affected_process_label', 'like', $search);
            });
        }

        if (! empty($filters['health'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['health'])));
            $values = array_values(array_intersect($values, HardwareDeviceHealth::all()));
            if ($values) {
                $query->whereIn('health', $values);
            }
        }

        if (! empty($filters['physical_location']) && in_array($filters['physical_location'], HardwarePhysicalLocation::all(), true)) {
            $query->where('physical_location', $filters['physical_location']);
        }

        if (array_key_exists('service_mode', $filters) && $filters['service_mode'] !== null && $filters['service_mode'] !== '') {
            $query->where('service_mode', filter_var($filters['service_mode'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filters that hop through document_print_attempts /
        // operational_documents — restrict the printer set to those that
        // currently have a matching open attempt.
        if (! empty($filters['document_type']) && in_array($filters['document_type'], DocumentType::all(), true)) {
            $matchingPrinterIds = OperationalDocument::query()
                ->where('document_type', $filters['document_type'])
                ->whereNotNull('printer_id')
                ->whereIn('print_status', [
                    DocumentPrintStatus::QUEUED,
                    DocumentPrintStatus::PRINTING,
                    DocumentPrintStatus::FAILED,
                ])
                ->pluck('printer_id')
                ->unique()
                ->all();
            $query->whereIn('id', $matchingPrinterIds);
        }

        if (! empty($filters['job_status']) && in_array($filters['job_status'], DocumentPrintStatus::all(), true)) {
            $matchingPrinterIds = DocumentPrintAttempt::query()
                ->where('status', $filters['job_status'])
                ->whereNotNull('printer_id')
                ->pluck('printer_id')
                ->unique()
                ->all();
            $query->whereIn('id', $matchingPrinterIds);
        }

        if (array_key_exists('exit_blocking', $filters) && $filters['exit_blocking'] !== null && $filters['exit_blocking'] !== '') {
            $wantBlocking = filter_var($filters['exit_blocking'], FILTER_VALIDATE_BOOLEAN);
            if ($wantBlocking) {
                $matchingPrinterIds = OperationalDocument::query()
                    ->where('is_exit_blocking', true)
                    ->whereNotNull('printer_id')
                    ->whereIn('print_status', [
                        DocumentPrintStatus::QUEUED,
                        DocumentPrintStatus::PRINTING,
                        DocumentPrintStatus::FAILED,
                    ])
                    ->pluck('printer_id')
                    ->unique()
                    ->all();
                $query->whereIn('id', $matchingPrinterIds);
            }
        }

        if (array_key_exists('failed_today', $filters) && filter_var($filters['failed_today'] ?? null, FILTER_VALIDATE_BOOLEAN)) {
            $matchingPrinterIds = DocumentPrintAttempt::query()
                ->where('status', DocumentPrintStatus::FAILED)
                ->whereDate('completed_at', now()->toDateString())
                ->whereNotNull('printer_id')
                ->pluck('printer_id')
                ->unique()
                ->all();
            $query->whereIn('id', $matchingPrinterIds);
        }
    }

    /* ============================================================
     * Detail-mode print history
     * ============================================================ */

    /**
     * Last 20 print attempts for this printer, newest first. Used by the
     * detail endpoint.
     *
     * @return Collection<int,DocumentPrintAttempt>
     */
    public function printHistoryFor(HardwareDevice $device): Collection
    {
        return DocumentPrintAttempt::query()
            ->where('printer_id', $device->id)
            ->orderByDesc('requested_at')
            ->orderByDesc('attempt_no')
            ->limit(20)
            ->get();
    }
}
