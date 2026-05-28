<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentPrintStatus;
use App\Enums\DocumentType;
use App\Enums\HardwareDeviceType;
use App\Http\Requests\Printer\ReroutePrintJobRequest;
use App\Http\Requests\Printer\RetryPrintJobRequest;
use App\Http\Resources\PrinterJobResource;
use App\Http\Resources\PrinterTabResource;
use App\Models\AuditLog;
use App\Models\DocumentPrintAttempt;
use App\Models\HardwareDevice;
use App\Models\OperationalDocument;
use App\Services\ApiResponse;
use App\Services\HardwareDevice\PrinterTabService;
use Illuminate\Http\Request;

/**
 * V1.4 §6 — internal Printers tab.
 *
 * Read-only composite over hardware_devices + document_print_attempts +
 * operational_documents. NO write endpoints here — service-mode writes
 * live on the parent Hardware Devices registry (/api/hardware-devices),
 * and reprint actions live on
 * /api/documents-reports/operational-documents/{id}/reprint.
 */
class PrinterTabController extends ApiController
{
    public function __construct(protected PrinterTabService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = PrinterTabResource::collection($paginator->items());

        $lastUpdated = HardwareDevice::query()
            ->where('device_type', HardwareDeviceType::PRINTER)
            ->max('last_event_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            'Printers retrieved'
        );
    }

    public function show(string $deviceId)
    {
        $device = HardwareDevice::query()
            ->where('device_type', HardwareDeviceType::PRINTER)
            ->find($deviceId);
        if (! $device) {
            return $this->error('Printer not found', 'PRINTER_NOT_FOUND', 404);
        }

        // Last 20 print attempts for this printer, newest first, with the
        // resolved document_no + document_type so the FE doesn't need a
        // second fetch per row.
        $attempts = $this->service->printHistoryFor($device);
        $docIds = $attempts->pluck('document_id')->filter()->unique()->all();
        $docMap = empty($docIds)
            ? collect()
            : OperationalDocument::query()
                ->whereIn('id', $docIds)
                ->get(['id', 'document_no', 'document_type', 'is_exit_blocking'])
                ->keyBy('id');

        $printHistory = $attempts->map(function ($a) use ($docMap) {
            $doc = $docMap->get($a->document_id);
            return [
                'id' => $a->id,
                'attemptNo' => (int) $a->attempt_no,
                'status' => [
                    'value' => $a->status,
                    'label' => DocumentPrintStatus::label($a->status),
                    'tone' => DocumentPrintStatus::tone($a->status),
                ],
                'documentId' => $a->document_id,
                'documentNo' => $doc?->document_no,
                'documentType' => $doc === null ? null : [
                    'value' => $doc->document_type,
                    'label' => DocumentType::label($doc->document_type),
                ],
                'isExitBlocking' => (bool) ($doc?->is_exit_blocking ?? false),
                'printJobId' => $a->print_job_id,
                'requestedAt' => $a->requested_at?->toIso8601String(),
                'completedAt' => $a->completed_at?->toIso8601String(),
                'failureReason' => $a->failure_reason,
                'isReprint' => (bool) $a->is_reprint,
                'reprintReason' => $a->reprint_reason,
            ];
        })->all();

        $auditRows = AuditLog::query()
            ->where('entity_type', $device->getMorphClass())
            ->where('entity_id', $device->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actorUserId' => $a->actor_user_id,
                'actorName' => $a->actor_name,
                'reason' => $a->reason,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])
            ->all();

        $resource = (new PrinterTabResource($device))
            ->additional([
                'printHistory' => $printHistory,
                'auditRows' => $auditRows,
            ]);

        return $this->success($resource, 'Printer detail retrieved');
    }

    /* ============================================================
     * V1.4 §6 jobs feed + §10 safe writes
     *
     * Three additive endpoints on top of the read surface above:
     *   - GET  /{id}/jobs                    paginated attempt feed
     *   - POST /{id}/jobs/{attemptId}/retry  retry failed/cancelled job
     *   - POST /{id}/jobs/{attemptId}/reroute reroute failed job
     * ============================================================ */

    public function jobs(Request $request, string $printerId)
    {
        $printer = $this->findPrinter($printerId);
        if (! $printer) {
            return $this->error('Printer not found', 'PRINTER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $paginator = $this->service->jobsForPrinter(
            $printer,
            $request->only(['status', 'date_from', 'date_to']),
            $perPage
        );

        return ApiResponse::list(
            PrinterJobResource::collection($paginator->items()),
            $paginator,
            null,
            $printer->last_event_at,
            'Printer job feed retrieved'
        );
    }

    public function retryJob(RetryPrintJobRequest $request, string $printerId, string $attemptId)
    {
        $printer = $this->findPrinter($printerId);
        if (! $printer) {
            return $this->error('Printer not found', 'PRINTER_NOT_FOUND', 404);
        }
        // Confirm the attempt actually belongs to this printer — the URL
        // says so; the DB must agree. Either soft-FK column (or the
        // legacy printer_name match) is acceptable.
        if (! $this->attemptBelongsToPrinter($attemptId, $printer)) {
            return $this->error(
                'Print attempt does not belong to this printer',
                'PRINT_ATTEMPT_NOT_FOUND_FOR_PRINTER',
                404
            );
        }

        try {
            $result = $this->service->retryJob($attemptId, $request->validated()['reason'] ?? null);
        } catch (\DomainException $e) {
            return $this->mapServiceException($e);
        }

        return $this->success(
            [
                'newAttempt' => (new PrinterJobResource($result['newAttempt']))->toArray(request()),
                'printer' => (new PrinterTabResource($printer))->toArray(request()),
            ],
            'Print job retry queued'
        );
    }

    public function rerouteJob(ReroutePrintJobRequest $request, string $printerId, string $attemptId)
    {
        $printer = $this->findPrinter($printerId);
        if (! $printer) {
            return $this->error('Printer not found', 'PRINTER_NOT_FOUND', 404);
        }
        if (! $this->attemptBelongsToPrinter($attemptId, $printer)) {
            return $this->error(
                'Print attempt does not belong to this printer',
                'PRINT_ATTEMPT_NOT_FOUND_FOR_PRINTER',
                404
            );
        }
        $data = $request->validated();
        if ($data['replacement_printer_id'] === $printer->id) {
            return $this->error(
                'Replacement printer must differ from the original.',
                'PRINTER_REPLACEMENT_INVALID',
                422,
                ['replacement_printer_id' => $data['replacement_printer_id']]
            );
        }

        try {
            $result = $this->service->rerouteToReplacement(
                $attemptId,
                $data['replacement_printer_id'],
                $data['reason']
            );
        } catch (\DomainException $e) {
            return $this->mapServiceException($e);
        }

        return $this->success(
            [
                'newAttempt' => (new PrinterJobResource($result['newAttempt']))->toArray(request()),
                'replacement' => (new PrinterTabResource($result['replacement']))->toArray(request()),
            ],
            'Print job rerouted'
        );
    }

    /* ----- internals ----- */

    protected function findPrinter(string $id): ?HardwareDevice
    {
        return HardwareDevice::query()
            ->where('device_type', HardwareDeviceType::PRINTER)
            ->find($id);
    }

    protected function attemptBelongsToPrinter(string $attemptId, HardwareDevice $printer): bool
    {
        return DocumentPrintAttempt::query()
            ->where('id', $attemptId)
            ->where(function ($q) use ($printer) {
                $q->where('printer_hardware_id', $printer->id)
                    ->orWhere('printer_id', $printer->id)
                    ->orWhere('printer_name', $printer->asset_tag);
            })
            ->exists();
    }

    /**
     * Translate the service's typed \DomainException strings into the
     * V1.4 §6 / §10 HTTP error envelope. The error codes match the
     * names in the spec / brief, not the exception messages.
     */
    protected function mapServiceException(\DomainException $e): \Illuminate\Http\JsonResponse
    {
        $code = $e->getMessage();
        return match ($code) {
            'PRINT_ATTEMPT_NOT_FOUND' =>
                $this->error('Print attempt not found', 'PRINT_ATTEMPT_NOT_FOUND', 404),
            'PRINTER_JOB_NOT_RETRYABLE' =>
                $this->error('Print attempt is not in a retryable state', 'PRINTER_JOB_NOT_RETRYABLE', 409),
            'PRINTER_JOB_NOT_REROUTABLE' =>
                $this->error('Only failed print attempts can be rerouted', 'PRINTER_JOB_NOT_REROUTABLE', 409),
            'PRINTER_REPLACEMENT_INVALID' =>
                $this->error(
                    'Replacement printer is not a usable printer (must exist, be in printer device_type, not in service mode, and not in fault/offline).',
                    'PRINTER_REPLACEMENT_INVALID',
                    422
                ),
            default => $this->error('Print job action failed', 'PRINTER_ACTION_FAILED', 500),
        };
    }
}
