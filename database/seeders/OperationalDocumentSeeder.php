<?php

namespace Database\Seeders;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\DocumentPrintStatus;
use App\Enums\DocumentType;
use App\Models\DocumentPrintAttempt;
use App\Models\OperationalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demo operational documents covering the lifecycle states the UI must
 * render (V1.2 §12 + §17): pending, generated, queued, printed, print_failed,
 * reprinted (with multi-attempt history), handed_over, blocked, invalidated.
 *
 * Soft-FK binding: looks up LO-2026-* / PV-2026-* rows by code rather than
 * relying on seeder order — falls back to placeholders so seeding stays
 * idempotent and tolerant of partial demo data.
 */
class OperationalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $orderRows = $this->fetchOrderBindings();
        $visitRows = $this->fetchVisitBindings();

        $now = now();

        $docs = [
            // 1. Pending — generation has not run yet. Blocks exit.
            [
                'document_no' => 'CERT-2026-0001',
                'document_type' => DocumentType::CERTIFICATE,
                'lifecycle_status' => DocumentLifecycleStatus::PENDING,
                'print_status' => DocumentPrintStatus::NOT_REQUESTED,
                'is_exit_blocking' => true,
                'blocking_reason' => 'Awaiting analysis approval before certificate generation.',
                'blocker_type' => 'quality',
                'order_key' => 'LO-2026-0001',
                'product_quality' => 'H2 5.0',
                'attempts' => [],
            ],

            // 2. Generated — file exists, print not yet requested.
            [
                'document_no' => 'DN-2026-0001',
                'document_type' => DocumentType::DELIVERY_NOTE,
                'lifecycle_status' => DocumentLifecycleStatus::GENERATED,
                'print_status' => DocumentPrintStatus::NOT_REQUESTED,
                'is_exit_blocking' => false,
                'order_key' => 'LO-2026-0002',
                'visit_key' => 'PV-2026-0019',
                'generated_at' => $now->copy()->subMinutes(45),
                'version' => 'v1',
                'template_name' => 'delivery_note_default',
                'template_version' => '2026-01',
                'file_url' => '/storage/demo/dn-2026-0001.pdf',
                'attempts' => [],
            ],

            // 3. Queued for print.
            [
                'document_no' => 'CERT-2026-0002',
                'document_type' => DocumentType::CERTIFICATE,
                'lifecycle_status' => DocumentLifecycleStatus::QUEUED_FOR_PRINT,
                'print_status' => DocumentPrintStatus::QUEUED,
                'is_exit_blocking' => false,
                'order_key' => 'LO-2026-0002',
                'visit_key' => 'PV-2026-0019',
                'generated_at' => $now->copy()->subMinutes(20),
                'queued_at' => $now->copy()->subMinutes(5),
                'printer_name' => 'PR-01',
                'print_job_id' => 'JOB-9001',
                'attempts' => [
                    [
                        'status' => DocumentPrintStatus::QUEUED,
                        'printer_name' => 'PR-01',
                        'print_job_id' => 'JOB-9001',
                        'requested_at' => $now->copy()->subMinutes(5),
                    ],
                ],
            ],

            // 4. Printed — successful single attempt.
            [
                'document_no' => 'CERT-2026-0003',
                'document_type' => DocumentType::CERTIFICATE,
                'lifecycle_status' => DocumentLifecycleStatus::PRINTED,
                'print_status' => DocumentPrintStatus::PRINTED,
                'is_exit_blocking' => false,
                'order_key' => 'LO-2026-0003',
                'generated_at' => $now->copy()->subHours(2),
                'queued_at' => $now->copy()->subHours(2),
                'printed_at' => $now->copy()->subHours(2)->addMinutes(1),
                'printer_name' => 'PR-01',
                'print_job_id' => 'JOB-8801',
                'file_url' => '/storage/demo/cert-2026-0003.pdf',
                'attempts' => [
                    [
                        'status' => DocumentPrintStatus::PRINTED,
                        'printer_name' => 'PR-01',
                        'print_job_id' => 'JOB-8801',
                        'requested_at' => $now->copy()->subHours(2),
                        'completed_at' => $now->copy()->subHours(2)->addMinutes(1),
                    ],
                ],
            ],

            // 5. Print failed — exit blocking. Single failed attempt.
            [
                'document_no' => 'DN-2026-0002',
                'document_type' => DocumentType::DELIVERY_NOTE,
                'lifecycle_status' => DocumentLifecycleStatus::PRINT_FAILED,
                'print_status' => DocumentPrintStatus::FAILED,
                'is_exit_blocking' => true,
                'blocking_reason' => 'Printer PR-02 reported a paper jam; reprint required.',
                'blocker_type' => 'print',
                'order_key' => 'LO-2026-0003',
                'generated_at' => $now->copy()->subMinutes(30),
                'queued_at' => $now->copy()->subMinutes(25),
                'printer_name' => 'PR-02',
                'print_job_id' => 'JOB-8902',
                'last_failure_reason' => 'Printer paper jam (code E-0042)',
                'attempts' => [
                    [
                        'status' => DocumentPrintStatus::FAILED,
                        'printer_name' => 'PR-02',
                        'print_job_id' => 'JOB-8902',
                        'requested_at' => $now->copy()->subMinutes(25),
                        'completed_at' => $now->copy()->subMinutes(22),
                        'failure_reason' => 'Printer paper jam (code E-0042)',
                    ],
                ],
            ],

            // 6. Reprinted — failed then successfully reprinted with reason.
            [
                'document_no' => 'CERT-2026-0004',
                'document_type' => DocumentType::CERTIFICATE,
                'lifecycle_status' => DocumentLifecycleStatus::REPRINTED,
                'print_status' => DocumentPrintStatus::REPRINTED,
                'is_exit_blocking' => false,
                'order_key' => 'LO-2026-0004',
                'visit_key' => 'PV-2026-0019',
                'generated_at' => $now->copy()->subHours(4),
                'queued_at' => $now->copy()->subHours(4),
                'printed_at' => $now->copy()->subHours(3)->subMinutes(40),
                'printer_name' => 'PR-01',
                'print_job_id' => 'JOB-8500',
                'reprint_count' => 1,
                'file_url' => '/storage/demo/cert-2026-0004.pdf',
                'attempts' => [
                    [
                        'status' => DocumentPrintStatus::FAILED,
                        'printer_name' => 'PR-02',
                        'print_job_id' => 'JOB-8499',
                        'requested_at' => $now->copy()->subHours(4),
                        'completed_at' => $now->copy()->subHours(4)->addMinutes(2),
                        'failure_reason' => 'Printer offline during print attempt',
                    ],
                    [
                        'status' => DocumentPrintStatus::PRINTED,
                        'printer_name' => 'PR-01',
                        'print_job_id' => 'JOB-8500',
                        'requested_at' => $now->copy()->subHours(3)->subMinutes(45),
                        'completed_at' => $now->copy()->subHours(3)->subMinutes(40),
                        'is_reprint' => true,
                        'reprint_reason' => 'Original print failed (PR-02 offline); reprinted on PR-01.',
                    ],
                ],
            ],

            // 7. Handed over — terminal success state.
            [
                'document_no' => 'DN-2026-0003',
                'document_type' => DocumentType::DELIVERY_NOTE,
                'lifecycle_status' => DocumentLifecycleStatus::HANDED_OVER,
                'print_status' => DocumentPrintStatus::PRINTED,
                'is_exit_blocking' => false,
                'order_key' => 'LO-2026-0004',
                'generated_at' => $now->copy()->subHours(6),
                'queued_at' => $now->copy()->subHours(6),
                'printed_at' => $now->copy()->subHours(6)->addMinutes(1),
                'handed_over_at' => $now->copy()->subHours(5),
                'handover_note' => 'Handed to driver at exit gate.',
                'printer_name' => 'PR-01',
                'print_job_id' => 'JOB-8200',
                'file_url' => '/storage/demo/dn-2026-0003.pdf',
                'attempts' => [
                    [
                        'status' => DocumentPrintStatus::PRINTED,
                        'printer_name' => 'PR-01',
                        'print_job_id' => 'JOB-8200',
                        'requested_at' => $now->copy()->subHours(6),
                        'completed_at' => $now->copy()->subHours(6)->addMinutes(1),
                    ],
                ],
            ],

            // 8. Blocked — quality blocker preventing generation entirely.
            [
                'document_no' => 'QM-2026-0001',
                'document_type' => DocumentType::QM_DOCUMENT,
                'lifecycle_status' => DocumentLifecycleStatus::BLOCKED,
                'print_status' => DocumentPrintStatus::NOT_REQUESTED,
                'is_exit_blocking' => true,
                'blocking_reason' => 'Open clarification case prevents QM document release.',
                'blocker_type' => 'clarification',
                'order_key' => 'LO-2026-0001',
                'blocked_at' => $now->copy()->subHours(1),
                'attempts' => [],
            ],
        ];

        foreach ($docs as $row) {
            $attempts = $row['attempts'] ?? [];
            unset($row['attempts']);

            $orderKey = $row['order_key'] ?? null;
            $visitKey = $row['visit_key'] ?? null;
            unset($row['order_key'], $row['visit_key']);

            $order = $orderKey ? ($orderRows[$orderKey] ?? null) : null;
            $visit = $visitKey ? ($visitRows[$visitKey] ?? null) : null;

            $payload = array_merge([
                'id' => (string) Str::uuid(),
                'order_id' => $order['id'] ?? null,
                'order_no' => $order['order_no'] ?? $orderKey,
                'sap_order_no' => $order['sap_order_no'] ?? null,
                'plant_visit_id' => $visit['id'] ?? null,
                'visit_no' => $visit['visit_no'] ?? $visitKey,
                'driver_name' => $order['driver_name'] ?? null,
                'trailer_label' => $order['trailer_label'] ?? null,
                'customer_name' => $order['customer_name'] ?? null,
                'carrier_name' => $order['carrier_name'] ?? null,
                'generated_by_source' => 'system',
                'snapshot_payload' => [
                    'order_no' => $order['order_no'] ?? $orderKey,
                    'driver_name' => $order['driver_name'] ?? null,
                    'trailer_label' => $order['trailer_label'] ?? null,
                    'frozen_at' => Carbon::parse($row['generated_at'] ?? $now)->toIso8601String(),
                ],
            ], $row);

            $doc = OperationalDocument::firstOrCreate(
                ['document_no' => $payload['document_no']],
                $payload
            );

            foreach ($attempts as $i => $attempt) {
                DocumentPrintAttempt::firstOrCreate(
                    [
                        'document_id' => $doc->id,
                        'attempt_no' => $i + 1,
                    ],
                    array_merge([
                        'id' => (string) Str::uuid(),
                        'created_at' => $attempt['requested_at'] ?? $now,
                    ], $attempt)
                );
            }
        }
    }

    /**
     * @return array<string,array{id:string,order_no:string,sap_order_no:?string,driver_name:?string,trailer_label:?string,customer_name:?string,carrier_name:?string}>
     */
    private function fetchOrderBindings(): array
    {
        if (! Schema::hasTable('loading_orders')) {
            return [];
        }
        $rows = DB::table('loading_orders')
            ->whereIn('order_no', ['LO-2026-0001', 'LO-2026-0002', 'LO-2026-0003', 'LO-2026-0004'])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->order_no] = [
                'id' => $r->id,
                'order_no' => $r->order_no,
                'sap_order_no' => $r->sap_reference ?? null,
                'driver_name' => $r->assigned_driver_name ?? null,
                'trailer_label' => $r->assigned_trailer_label ?? null,
                'customer_name' => $r->customer_name ?? null,
                'carrier_name' => $r->carrier_name ?? null,
            ];
        }
        return $out;
    }

    /**
     * @return array<string,array{id:string,visit_no:string}>
     */
    private function fetchVisitBindings(): array
    {
        if (! Schema::hasTable('plant_visits')) {
            return [];
        }
        $rows = DB::table('plant_visits')
            ->whereIn('visit_no', ['PV-2026-0019', 'PV-2026-0022'])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->visit_no] = ['id' => $r->id, 'visit_no' => $r->visit_no];
        }
        return $out;
    }
}
