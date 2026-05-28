<?php

namespace Database\Seeders;

use App\Enums\PrinterJobStatus;
use App\Models\DocumentPrintAttempt;
use App\Models\HardwareDevice;
use App\Models\OperationalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * V1.4 §6 Printers tab — demo job feed.
 *
 * Generates ~10 `document_print_attempts` rows across HD-PRINTER-DRV-01
 * (Driver Terminal Printer, healthy) and HD-PRINTER-OPR-01 (Operator /
 * Control Room Printer, FAULT per HardwareDeviceSeeder). Status mix is
 * deliberately weighted so the FE can see every Printer tab state path:
 * printed (4), failed (3), queued (2), cancelled (1).
 *
 * DatabaseSeeder uses `WithoutModelEvents`, so the DocumentPrintAttempt
 * model's booted() UUID + `requested_at` + `created_at` defaults DO NOT
 * fire. We pass them explicitly. Same pattern as AnalysisDeviceSeeder
 * fix in commit d4a50ae.
 *
 * Idempotent: keyed on `(document_id, attempt_no)` via the unique index;
 * `attempt_no` values start at 51 / 52 / ... so they never collide with
 * the per-document attempt rows the OperationalDocumentSeeder already
 * seeds for the document at attempt 1/2.
 */
class PrinterJobDemoSeeder extends Seeder
{
    /**
     * Where this seeder's rows start. The base OperationalDocument
     * seeder writes attempts 1..N per document; we leave a wide gap so
     * we can never collide with future D1 demo additions.
     */
    private const ATTEMPT_NO_BASE = 51;

    public function run(): void
    {
        $drvId = HardwareDevice::query()
            ->where('asset_tag', 'HD-PRINTER-DRV-01')
            ->value('id');
        $oprId = HardwareDevice::query()
            ->where('asset_tag', 'HD-PRINTER-OPR-01')
            ->value('id');

        if (! $drvId || ! $oprId) {
            // Track A's HardwareDeviceSeeder hasn't run yet — leave the
            // demo feed empty rather than fabricating dangling soft FKs.
            return;
        }

        $docs = OperationalDocument::query()
            ->orderBy('document_no')
            ->limit(10)
            ->get();
        if ($docs->isEmpty()) {
            return;
        }

        $now = now();

        $rows = [
            // ---- 4 successful jobs on HD-PRINTER-DRV-01 (healthy) ----
            $this->row(
                $docs->get(0), $drvId, 'HD-PRINTER-DRV-01',
                PrinterJobStatus::PRINTED,
                $now->copy()->subHours(2),
                $now->copy()->subHours(2)->addMinutes(1)
            ),
            $this->row(
                $docs->get(1) ?? $docs->get(0), $drvId, 'HD-PRINTER-DRV-01',
                PrinterJobStatus::PRINTED,
                $now->copy()->subHours(3),
                $now->copy()->subHours(3)->addMinutes(1)
            ),
            $this->row(
                $docs->get(2) ?? $docs->get(0), $drvId, 'HD-PRINTER-DRV-01',
                PrinterJobStatus::PRINTED,
                $now->copy()->subHours(4),
                $now->copy()->subHours(4)->addMinutes(1)
            ),
            $this->row(
                $docs->get(3) ?? $docs->get(0), $drvId, 'HD-PRINTER-DRV-01',
                PrinterJobStatus::PRINTED,
                $now->copy()->subHours(5),
                $now->copy()->subHours(5)->addMinutes(1)
            ),

            // ---- 3 failed jobs on HD-PRINTER-OPR-01 (printer fault) ----
            $this->row(
                $docs->get(4) ?? $docs->get(0), $oprId, 'HD-PRINTER-OPR-01',
                PrinterJobStatus::FAILED,
                $now->copy()->subHours(1),
                $now->copy()->subHours(1)->addSeconds(20),
                'Out of paper'
            ),
            $this->row(
                $docs->get(5) ?? $docs->get(0), $oprId, 'HD-PRINTER-OPR-01',
                PrinterJobStatus::FAILED,
                $now->copy()->subMinutes(40),
                $now->copy()->subMinutes(40)->addSeconds(15),
                'Connectivity lost'
            ),
            $this->row(
                $docs->get(6) ?? $docs->get(0), $oprId, 'HD-PRINTER-OPR-01',
                PrinterJobStatus::FAILED,
                $now->copy()->subMinutes(10),
                $now->copy()->subMinutes(10)->addSeconds(15),
                'Toner empty'
            ),

            // ---- 2 queued jobs awaiting retry ----
            $this->row(
                $docs->get(7) ?? $docs->get(0), $oprId, 'HD-PRINTER-OPR-01',
                PrinterJobStatus::QUEUED,
                $now->copy()->subMinutes(5),
                null
            ),
            $this->row(
                $docs->get(8) ?? $docs->get(0), $drvId, 'HD-PRINTER-DRV-01',
                PrinterJobStatus::QUEUED,
                $now->copy()->subMinutes(2),
                null
            ),

            // ---- 1 cancelled job (rounds out the tone palette) ----
            $this->row(
                $docs->get(9) ?? $docs->get(0), $oprId, 'HD-PRINTER-OPR-01',
                PrinterJobStatus::CANCELLED,
                $now->copy()->subHours(6),
                $now->copy()->subHours(6)->addMinutes(2),
                'Operator cancelled before printer recovered'
            ),
        ];

        $attemptNo = self::ATTEMPT_NO_BASE;
        foreach ($rows as $row) {
            // Look up the existing id so a re-seed preserves UUIDs (any
            // dangling audit_logs / event_logs FKs still resolve).
            $existingId = DocumentPrintAttempt::query()
                ->where('document_id', $row['document_id'])
                ->where('attempt_no', $attemptNo)
                ->value('id');

            DocumentPrintAttempt::updateOrCreate(
                [
                    'document_id' => $row['document_id'],
                    'attempt_no' => $attemptNo,
                ],
                array_merge($row, [
                    'id' => $existingId ?? (string) Str::uuid(),
                    // WithoutModelEvents bypasses the model's booted()
                    // hook, so the timestamps need explicit values.
                    'requested_at' => $row['requested_at'] ?? now(),
                    'created_at' => $row['requested_at'] ?? now(),
                ])
            );

            $attemptNo++;
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function row(
        OperationalDocument $doc,
        string $printerHardwareId,
        string $assetTag,
        string $status,
        Carbon $requestedAt,
        ?Carbon $completedAt,
        ?string $failureReason = null
    ): array {
        return [
            'document_id' => $doc->id,
            'status' => $status,
            // Set BOTH the legacy `printer_id` (Track A reads from this)
            // and the new `printer_hardware_id` (V1.4 §6 reader). They
            // point at the same hardware_devices.id.
            'printer_id' => $printerHardwareId,
            'printer_hardware_id' => $printerHardwareId,
            'printer_name' => $assetTag,
            'print_job_id' => 'demo-' . substr((string) Str::uuid(), 0, 8),
            'requested_at' => $requestedAt,
            'completed_at' => $completedAt,
            'failure_reason' => $failureReason,
            'is_reprint' => false,
            'reprint_reason' => null,
            'retry_of_attempt_id' => null,
            'replacement_of_attempt_id' => null,
            'correlation_id' => 'seed-printer-job-demo',
        ];
    }
}
