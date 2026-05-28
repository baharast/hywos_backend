<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentPrintStatus;
use App\Enums\DocumentType;
use App\Enums\HardwareDeviceType;
use App\Http\Resources\PrinterTabResource;
use App\Models\AuditLog;
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
}
