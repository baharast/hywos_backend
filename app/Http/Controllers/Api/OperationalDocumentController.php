<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\DocumentLifecycleStatus;
use App\Enums\DocumentPrintStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Requests\OperationalDocument\HandOverDocumentRequest;
use App\Http\Requests\OperationalDocument\InvalidateDocumentRequest;
use App\Http\Requests\OperationalDocument\PrintDocumentRequest;
use App\Http\Requests\OperationalDocument\ReprintDocumentRequest;
use App\Http\Resources\DocumentPrintAttemptResource;
use App\Http\Resources\OperationalDocumentResource;
use App\Models\DocumentPrintAttempt;
use App\Models\OperationalDocument;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Operational Documents (V1.2) — list + detail + lifecycle actions.
 *
 * Read endpoints: list, show, preview, events-audit.
 * Write endpoints: print, reprint, hand-over, invalidate.
 *
 * State transition matrix (V1.2 §12 + §13):
 *   pending           → blocked | cancelled               (driven by upstream prerequisites)
 *   generated         → queued_for_print | invalidated | cancelled
 *   queued_for_print  → printed | print_failed | invalidated
 *   printed           → handed_over | reprinted | invalidated
 *   print_failed      → queued_for_print (via reprint) | invalidated | cancelled
 *   reprinted         → handed_over | reprinted | invalidated
 *   handed_over       → invalidated                       (terminal otherwise)
 *   blocked           → pending | cancelled               (when upstream resolves)
 *   invalidated, cancelled = terminal
 *
 * Auth is disabled in dev — actor_user_id is null on audit + event rows.
 */
class OperationalDocumentController extends ApiController
{
    /* ============================================================
     * READ
     * ============================================================ */

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = OperationalDocument::query();
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = [
            'updated_at', 'created_at', 'generated_at', 'printed_at',
            'document_no', 'document_type', 'lifecycle_status', 'print_status',
        ];
        if (! in_array($column, $allowed, true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }

        // §9.5 Reset behavior — default order is "blocking/failed first then
        // latest updated". Respect explicit sort, but when the default is in
        // effect, bias blocking rows to the top.
        if ($column === 'updated_at' && $direction === 'desc' && ! $request->has('sort')) {
            $query->orderByDesc('is_exit_blocking');
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = OperationalDocumentResource::collection($paginator->items());

        $lastUpdated = OperationalDocument::query()->max('updated_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->summary(),
            $lastUpdated,
            __('masterdata.messages.documents_retrieved')
        );
    }

    public function show(string $id)
    {
        $doc = OperationalDocument::with('printAttempts')->find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        return $this->success(
            OperationalDocumentResource::detail($doc),
            __('masterdata.messages.document_retrieved')
        );
    }

    /**
     * Document file preview. Returns the stored file_url + metadata. No
     * actual file streaming yet — this is the placeholder per V1.2 §14
     * ("Provide placeholder support for preview/download and template
     * metadata") until a real file/PDF generator lands.
     */
    public function preview(string $id)
    {
        $doc = OperationalDocument::find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        if (empty($doc->file_url)) {
            return $this->error(
                __('masterdata.messages.document_no_preview'),
                'PREVIEW_UNAVAILABLE',
                404,
                ['lifecycleStatus' => $doc->lifecycle_status]
            );
        }

        return $this->success([
            'id' => $doc->id,
            'documentNo' => $doc->document_no,
            'fileUrl' => $doc->file_url,
            'templateName' => $doc->template_name,
            'templateVersion' => $doc->template_version,
            'version' => $doc->version,
        ], __('masterdata.messages.document_preview'));
    }

    /**
     * Surfaced as a separate endpoint so the Detail page's "Print History"
     * tab and "Events & Audit" tab can load independently of the heavy
     * detail payload.
     */
    public function printHistory(string $id)
    {
        $doc = OperationalDocument::find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        $attempts = $doc->printAttempts()->orderBy('attempt_no')->get();
        return $this->success(
            DocumentPrintAttemptResource::collection($attempts),
            __('masterdata.messages.document_print_history')
        );
    }

    /* ============================================================
     * WRITE — lifecycle actions
     * ============================================================ */

    public function print(PrintDocumentRequest $request, string $id, AuditLogger $audit, EventLogger $events)
    {
        $doc = OperationalDocument::find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        if (! in_array($doc->lifecycle_status, [
            DocumentLifecycleStatus::GENERATED,
            DocumentLifecycleStatus::PRINT_FAILED,
        ], true)) {
            return $this->error(
                "Cannot print a document in status '{$doc->lifecycle_status}'. Use Reprint for printed documents.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $data = $request->validated();

        return DB::transaction(function () use ($doc, $data, $audit, $events) {
            $old = $audit->snapshotModel($doc);

            $attemptNo = ((int) $doc->printAttempts()->max('attempt_no')) + 1;

            $attempt = DocumentPrintAttempt::create([
                'id' => (string) Str::uuid(),
                'document_id' => $doc->id,
                'attempt_no' => $attemptNo,
                'status' => DocumentPrintStatus::QUEUED,
                'printer_id' => $data['printer_id'] ?? null,
                'printer_name' => $data['printer_name'] ?? null,
                'requested_at' => now(),
                'is_reprint' => false,
                'correlation_id' => request()->header('X-Correlation-Id'),
            ]);

            $doc->lifecycle_status = DocumentLifecycleStatus::QUEUED_FOR_PRINT;
            $doc->print_status = DocumentPrintStatus::QUEUED;
            $doc->queued_at = now();
            $doc->printer_id = $data['printer_id'] ?? $doc->printer_id;
            $doc->printer_name = $data['printer_name'] ?? $doc->printer_name;
            $doc->last_failure_reason = null;
            // print_failed was exit-blocking; queueing the print clears the
            // print-blocker so exit gating reflects the in-flight attempt.
            if ($doc->blocker_type === 'print') {
                $doc->is_exit_blocking = false;
                $doc->blocking_reason = null;
                $doc->blocker_type = null;
            }
            $doc->save();

            $fresh = $doc->fresh();
            $audit->record(
                $doc,
                AuditAction::DOCUMENT_QUEUED_FOR_PRINT,
                $old,
                $audit->snapshotModel($fresh),
                null,
                null
            );
            $events->record(
                'document.queued_for_print',
                $doc,
                "Document {$doc->document_no} queued for print (attempt {$attemptNo})",
                [
                    'attempt_no' => $attemptNo,
                    'attempt_id' => $attempt->id,
                    'printer_name' => $attempt->printer_name,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                OperationalDocumentResource::detail($fresh->load('printAttempts')),
                __('masterdata.messages.document_queued')
            );
        });
    }

    public function reprint(ReprintDocumentRequest $request, string $id, AuditLogger $audit, EventLogger $events)
    {
        $doc = OperationalDocument::find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        if (! in_array($doc->lifecycle_status, [
            DocumentLifecycleStatus::PRINTED,
            DocumentLifecycleStatus::PRINT_FAILED,
            DocumentLifecycleStatus::REPRINTED,
            DocumentLifecycleStatus::HANDED_OVER,
        ], true)) {
            return $this->error(
                "Cannot reprint a document in status '{$doc->lifecycle_status}'.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $data = $request->validated();

        return DB::transaction(function () use ($doc, $data, $audit, $events) {
            $old = $audit->snapshotModel($doc);

            $attemptNo = ((int) $doc->printAttempts()->max('attempt_no')) + 1;

            $attempt = DocumentPrintAttempt::create([
                'id' => (string) Str::uuid(),
                'document_id' => $doc->id,
                'attempt_no' => $attemptNo,
                'status' => DocumentPrintStatus::QUEUED,
                'printer_id' => $data['printer_id'] ?? null,
                'printer_name' => $data['printer_name'] ?? null,
                'requested_at' => now(),
                'is_reprint' => true,
                'reprint_reason' => $data['reason'],
                'correlation_id' => request()->header('X-Correlation-Id'),
            ]);

            $doc->lifecycle_status = DocumentLifecycleStatus::QUEUED_FOR_PRINT;
            $doc->print_status = DocumentPrintStatus::QUEUED;
            $doc->queued_at = now();
            $doc->printer_id = $data['printer_id'] ?? $doc->printer_id;
            $doc->printer_name = $data['printer_name'] ?? $doc->printer_name;
            $doc->reprint_count = (int) $doc->reprint_count + 1;
            $doc->last_failure_reason = null;
            if ($doc->blocker_type === 'print') {
                $doc->is_exit_blocking = false;
                $doc->blocking_reason = null;
                $doc->blocker_type = null;
            }
            $doc->save();

            $fresh = $doc->fresh();
            $audit->record(
                $doc,
                AuditAction::DOCUMENT_REPRINTED,
                $old,
                $audit->snapshotModel($fresh),
                $data['reason'],
                null
            );
            $events->record(
                'document.reprinted',
                $doc,
                "Document {$doc->document_no} reprint requested (attempt {$attemptNo})",
                [
                    'attempt_no' => $attemptNo,
                    'attempt_id' => $attempt->id,
                    'printer_name' => $attempt->printer_name,
                    'reason' => $data['reason'],
                    'reprint_count' => $doc->reprint_count,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                OperationalDocumentResource::detail($fresh->load('printAttempts')),
                __('masterdata.messages.document_reprinted')
            );
        });
    }

    public function handOver(HandOverDocumentRequest $request, string $id, AuditLogger $audit, EventLogger $events)
    {
        $doc = OperationalDocument::find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        if (! in_array($doc->lifecycle_status, [
            DocumentLifecycleStatus::PRINTED,
            DocumentLifecycleStatus::REPRINTED,
        ], true)) {
            return $this->error(
                "Cannot mark handed-over from status '{$doc->lifecycle_status}'. Document must be printed first.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $data = $request->validated();

        return DB::transaction(function () use ($doc, $data, $audit, $events) {
            $old = $audit->snapshotModel($doc);

            $doc->lifecycle_status = DocumentLifecycleStatus::HANDED_OVER;
            $doc->handed_over_at = now();
            $doc->handover_note = $data['note'] ?? null;
            // handed_over_by_user_id stays null in dev — auth disabled.
            if ($doc->blocker_type === 'handover') {
                $doc->is_exit_blocking = false;
                $doc->blocking_reason = null;
                $doc->blocker_type = null;
            }
            $doc->save();

            $fresh = $doc->fresh();
            $audit->record(
                $doc,
                AuditAction::DOCUMENT_HANDED_OVER,
                $old,
                $audit->snapshotModel($fresh),
                $data['note'] ?? null,
                null
            );
            $events->record(
                'document.handed_over',
                $doc,
                "Document {$doc->document_no} handed over",
                ['note' => $data['note'] ?? null],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                OperationalDocumentResource::detail($fresh->load('printAttempts')),
                __('masterdata.messages.document_handed_over')
            );
        });
    }

    public function invalidate(InvalidateDocumentRequest $request, string $id, AuditLogger $audit, EventLogger $events)
    {
        $doc = OperationalDocument::find($id);
        if (! $doc) {
            return $this->error(__('masterdata.messages.document_not_found'), 'DOCUMENT_NOT_FOUND', 404);
        }

        if (in_array($doc->lifecycle_status, [
            DocumentLifecycleStatus::INVALIDATED,
            DocumentLifecycleStatus::CANCELLED,
        ], true)) {
            return $this->error(
                "Document is already in a terminal status ({$doc->lifecycle_status}).",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $data = $request->validated();
        $asCancelled = (bool) ($data['as_cancelled'] ?? false);

        return DB::transaction(function () use ($doc, $data, $asCancelled, $audit, $events) {
            $old = $audit->snapshotModel($doc);
            $previousStatus = $doc->lifecycle_status;

            if ($asCancelled) {
                $doc->lifecycle_status = DocumentLifecycleStatus::CANCELLED;
                $doc->cancelled_at = now();
                $doc->cancellation_reason = $data['reason'];
            } else {
                $doc->lifecycle_status = DocumentLifecycleStatus::INVALIDATED;
                $doc->invalidated_at = now();
                $doc->invalidation_reason = $data['reason'];
            }
            $doc->is_exit_blocking = false;
            $doc->blocking_reason = null;
            $doc->blocker_type = null;
            $doc->save();

            $fresh = $doc->fresh();
            $audit->record(
                $doc,
                AuditAction::DOCUMENT_INVALIDATED,
                $old,
                $audit->snapshotModel($fresh),
                $data['reason'],
                null
            );
            $events->record(
                $asCancelled ? 'document.cancelled' : 'document.invalidated',
                $doc,
                "Document {$doc->document_no} " . ($asCancelled ? 'cancelled' : 'invalidated'),
                [
                    'previous_status' => $previousStatus,
                    'new_status' => $doc->lifecycle_status,
                    'reason' => $data['reason'],
                ],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(
                OperationalDocumentResource::detail($fresh->load('printAttempts')),
                $asCancelled ? __('masterdata.messages.document_cancelled') : __('masterdata.messages.document_invalidated')
            );
        });
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('document_no', 'like', $search)
                    ->orWhere('order_no', 'like', $search)
                    ->orWhere('sap_order_no', 'like', $search)
                    ->orWhere('visit_no', 'like', $search)
                    ->orWhere('driver_name', 'like', $search)
                    ->orWhere('trailer_label', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('carrier_name', 'like', $search)
                    ->orWhere('printer_name', 'like', $search)
                    ->orWhere('print_job_id', 'like', $search);
            });
        }

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        if (! empty($filters['lifecycle_status'])) {
            $query->where('lifecycle_status', $filters['lifecycle_status']);
        }
        if (! empty($filters['print_status'])) {
            $query->where('print_status', $filters['print_status']);
        }
        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }
        if (! empty($filters['plant_visit_id'])) {
            $query->where('plant_visit_id', $filters['plant_visit_id']);
        }
        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }
        if (! empty($filters['trailer_id'])) {
            $query->where('trailer_id', $filters['trailer_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (! empty($filters['blocker_type'])) {
            $query->where('blocker_type', $filters['blocker_type']);
        }

        if (array_key_exists('is_exit_blocking', $filters) && $filters['is_exit_blocking'] !== null && $filters['is_exit_blocking'] !== '') {
            $query->where(
                'is_exit_blocking',
                filter_var($filters['is_exit_blocking'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        if (! empty($filters['date_from'])) {
            $query->where('generated_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('generated_at', '<=', $filters['date_to']);
        }
    }

    protected function summary(): array
    {
        $base = OperationalDocument::query();
        $activeStatuses = DocumentLifecycleStatus::activeStatuses();

        $totalActive = (clone $base)->whereIn('lifecycle_status', $activeStatuses)->count();
        $blockingExit = (clone $base)->where('is_exit_blocking', true)->count();
        $printFailed = (clone $base)
            ->where('lifecycle_status', DocumentLifecycleStatus::PRINT_FAILED)
            ->count();
        $pending = (clone $base)
            ->where('lifecycle_status', DocumentLifecycleStatus::PENDING)
            ->count();

        $byType = (clone $base)
            ->selectRaw('document_type, COUNT(*) as c')
            ->groupBy('document_type')
            ->pluck('c', 'document_type')
            ->toArray();

        $byLifecycle = (clone $base)
            ->whereIn('lifecycle_status', $activeStatuses)
            ->selectRaw('lifecycle_status, COUNT(*) as c')
            ->groupBy('lifecycle_status')
            ->pluck('c', 'lifecycle_status')
            ->toArray();

        return [
            'totalActive' => $totalActive,
            'blockingExit' => $blockingExit,
            'printFailed' => $printFailed,
            'pending' => $pending,
            'byType' => $byType,
            'byLifecycle' => $byLifecycle,
        ];
    }
}
