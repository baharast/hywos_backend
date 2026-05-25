<?php

namespace Database\Seeders;

use App\Enums\SapFeedbackType;
use App\Enums\SapHandlingStatus;
use App\Enums\SapSyncDirection;
use App\Enums\SapSyncResultStatus;
use App\Models\LoadingOrder;
use App\Models\SapSyncRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * SAP Sync demo data (V1.5 §11.1).
 *
 * Seeds 9 records covering the spec's required scenarios so the slot board
 * page renders meaningful summary counts (`total`, `success`, `pending`,
 * `failed`, `resolved`, `needsSupport`) and the row-action rules can be
 * exercised: linked-order rows show Open Loading Order; failed-before-import
 * rows show only Show Details.
 */
class SapSyncRecordSeeder extends Seeder
{
    public function run(): void
    {
        // Link the first batch to LO-2026-0002 (NEEDS_ASSIGNMENT, source=sap).
        $lo2 = LoadingOrder::query()->where('order_no', 'LO-2026-0002')->first();
        $lo3 = LoadingOrder::query()->where('order_no', 'LO-2026-0003')->first();
        $lo4 = LoadingOrder::query()->where('order_no', 'LO-2026-0004')->first();

        $now = now();

        // 1) Successful import — linked to LO-2026-0002.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450012982',
                'direction' => SapSyncDirection::IMPORT,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo2?->id,
                'order_no' => $lo2?->order_no,
                'customer_id' => $lo2?->customer_id,
                'customer_name' => $lo2?->customer_name,
                'carrier_id' => $lo2?->carrier_id,
                'carrier_name' => $lo2?->carrier_name,
                'product_quality' => $lo2?->product_quality ?? 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::SUCCESS,
                'handling_status' => SapHandlingStatus::NO_ACTION_NEEDED,
                'issue_reason' => null,
                'what_happened' => 'SAP order imported and Loading Order LO-2026-0002 created.',
                'next_action' => 'Open Loading Order to assign driver/trailer.',
                'sync_run_id' => 'RUN-2026-0525-A',
                'interface_id' => 'IF-SAP-ORDERS-V2',
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subHours(2),
                'last_attempt_at' => $now->copy()->subHours(2),
                'last_success_at' => $now->copy()->subHours(2),
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 2) Successful export feedback (order_status) — linked to LO-2026-0003.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450012999',
                'direction' => SapSyncDirection::EXPORT,
                'feedback_type' => SapFeedbackType::ORDER_STATUS,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo3?->id,
                'order_no' => $lo3?->order_no,
                'customer_id' => $lo3?->customer_id,
                'customer_name' => $lo3?->customer_name,
                'carrier_id' => $lo3?->carrier_id,
                'carrier_name' => $lo3?->carrier_name,
                'product_quality' => $lo3?->product_quality ?? 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::SUCCESS,
                'handling_status' => SapHandlingStatus::NO_ACTION_NEEDED,
                'issue_reason' => null,
                'what_happened' => 'Order status READY sent to SAP and acknowledged.',
                'next_action' => 'No action — feedback delivered.',
                'sync_run_id' => 'RUN-2026-0525-A',
                'interface_id' => 'IF-SAP-FEEDBACK-V1',
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subHours(1)->subMinutes(50),
                'last_attempt_at' => $now->copy()->subHours(1)->subMinutes(50),
                'last_success_at' => $now->copy()->subHours(1)->subMinutes(50),
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 3) Failed import — no local order_id (customer mapping issue).
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450013111',
                'direction' => SapSyncDirection::IMPORT,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => null,
                'order_no' => null,
                'customer_id' => null,
                'customer_name' => 'ACME Extern SA',
                'carrier_id' => null,
                'carrier_name' => null,
                'product_quality' => 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::FAILED,
                'handling_status' => SapHandlingStatus::NEEDS_SUPPORT,
                'issue_reason' => 'Customer mapping missing in SAP order payload.',
                'what_happened' => 'Import failed during customer resolution; no Loading Order was created.',
                'next_action' => 'Route to SAP Support to add customer mapping for ACME Extern SA.',
                'technical_message' => "Customer 'CUST-99' not found in local mapping table.",
                'connector_message' => 'MM_CUSTOMER_LOOKUP returned -1.',
                'sync_run_id' => 'RUN-2026-0525-B',
                'interface_id' => 'IF-SAP-ORDERS-V2',
                'retry_count' => 2,
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subHours(1)->subMinutes(20),
                'last_attempt_at' => $now->copy()->subMinutes(35),
                'last_success_at' => null,
                'attempts' => [
                    [
                        'attemptedAt' => $now->copy()->subHours(1)->subMinutes(20)->toIso8601String(),
                        'resultStatus' => SapSyncResultStatus::FAILED,
                        'issueReason' => 'Customer mapping missing in SAP order payload.',
                        'handlingStatus' => SapHandlingStatus::AUTO_RETRY_SCHEDULED,
                    ],
                    [
                        'attemptedAt' => $now->copy()->subMinutes(35)->toIso8601String(),
                        'resultStatus' => SapSyncResultStatus::FAILED,
                        'issueReason' => 'Customer mapping missing in SAP order payload.',
                        'handlingStatus' => SapHandlingStatus::NEEDS_SUPPORT,
                    ],
                ],
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 4) Failed export — document feedback, scheduled for auto-retry.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450013010',
                'direction' => SapSyncDirection::EXPORT,
                'feedback_type' => SapFeedbackType::DOCUMENT,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo4?->id,
                'order_no' => $lo4?->order_no,
                'customer_id' => $lo4?->customer_id,
                'customer_name' => $lo4?->customer_name,
                'carrier_id' => $lo4?->carrier_id,
                'carrier_name' => $lo4?->carrier_name,
                'product_quality' => $lo4?->product_quality ?? 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::FAILED,
                'handling_status' => SapHandlingStatus::AUTO_RETRY_SCHEDULED,
                'issue_reason' => 'SAP delivery note interface temporarily unavailable.',
                'what_happened' => 'Delivery note number could not be returned to SAP.',
                'next_action' => 'Wait for automatic retry (next attempt in ~10 minutes).',
                'technical_message' => 'HTTP 503 from SAP RFC gateway.',
                'connector_message' => 'IDoc DELVRY07 queued for retry.',
                'sync_run_id' => 'RUN-2026-0525-C',
                'interface_id' => 'IF-SAP-FEEDBACK-V1',
                'retry_count' => 1,
                'owner_role' => 'IT_Support',
                'event_time' => $now->copy()->subMinutes(25),
                'last_attempt_at' => $now->copy()->subMinutes(8),
                'last_success_at' => null,
                'attempts' => [
                    [
                        'attemptedAt' => $now->copy()->subMinutes(25)->toIso8601String(),
                        'resultStatus' => SapSyncResultStatus::FAILED,
                        'issueReason' => 'SAP delivery note interface temporarily unavailable.',
                        'handlingStatus' => SapHandlingStatus::AUTO_RETRY_SCHEDULED,
                    ],
                ],
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 5) Pending export — quantity feedback queued.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450012999',
                'direction' => SapSyncDirection::EXPORT,
                'feedback_type' => SapFeedbackType::QUANTITY,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo3?->id,
                'order_no' => $lo3?->order_no,
                'customer_id' => $lo3?->customer_id,
                'customer_name' => $lo3?->customer_name,
                'carrier_id' => $lo3?->carrier_id,
                'carrier_name' => $lo3?->carrier_name,
                'product_quality' => $lo3?->product_quality ?? 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::PENDING,
                'handling_status' => SapHandlingStatus::NO_ACTION_NEEDED,
                'issue_reason' => null,
                'what_happened' => 'Quantity feedback queued for the next outbound run.',
                'next_action' => 'No action — waiting for scheduled export window.',
                'sync_run_id' => null,
                'interface_id' => 'IF-SAP-FEEDBACK-V1',
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subMinutes(15),
                'last_attempt_at' => null,
                'last_success_at' => null,
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 6) Resolved — earlier import error was fixed in SAP, current state is OK.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450012970',
                'direction' => SapSyncDirection::IMPORT,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo2?->id,
                'order_no' => $lo2?->order_no,
                'customer_id' => $lo2?->customer_id,
                'customer_name' => $lo2?->customer_name,
                'carrier_id' => $lo2?->carrier_id,
                'carrier_name' => $lo2?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::RESOLVED,
                'handling_status' => SapHandlingStatus::RESOLVED,
                'issue_reason' => 'Previous mapping issue was resolved upstream.',
                'what_happened' => 'Earlier failed import was reprocessed successfully after SAP master-data fix.',
                'next_action' => 'No action — issue resolved.',
                'sync_run_id' => 'RUN-2026-0524-Z',
                'interface_id' => 'IF-SAP-ORDERS-V2',
                'retry_count' => 3,
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subDay()->subHours(2),
                'last_attempt_at' => $now->copy()->subDay(),
                'last_success_at' => $now->copy()->subDay(),
                'attempts' => [
                    [
                        'attemptedAt' => $now->copy()->subDay()->subHours(4)->toIso8601String(),
                        'resultStatus' => SapSyncResultStatus::FAILED,
                        'issueReason' => 'Mapping issue (resolved upstream).',
                        'handlingStatus' => SapHandlingStatus::NEEDS_SUPPORT,
                    ],
                    [
                        'attemptedAt' => $now->copy()->subDay()->toIso8601String(),
                        'resultStatus' => SapSyncResultStatus::SUCCESS,
                        'issueReason' => null,
                        'handlingStatus' => SapHandlingStatus::RESOLVED,
                    ],
                ],
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 7) Needs-support import — unknown product / mapping rejected.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450013120',
                'direction' => SapSyncDirection::IMPORT,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => null,
                'order_no' => null,
                'customer_id' => null,
                'customer_name' => 'Beta Hydrogen Logistics',
                'product_quality' => 'Hydrogen 6.0',
                'result_status' => SapSyncResultStatus::FAILED,
                'handling_status' => SapHandlingStatus::NEEDS_SUPPORT,
                'issue_reason' => 'Unknown product/quality "Hydrogen 6.0" — not configured for this site.',
                'what_happened' => 'Import rejected because product quality is unknown locally.',
                'next_action' => 'Route to Admin to add Hydrogen 6.0 to product master data.',
                'technical_message' => "Product code 'H2-6.0' not present in product_master.",
                'connector_message' => 'MM_PRODUCT_LOOKUP returned NULL.',
                'sync_run_id' => 'RUN-2026-0525-B',
                'interface_id' => 'IF-SAP-ORDERS-V2',
                'retry_count' => 5,
                'owner_role' => 'Dispatcher_Admin',
                'event_time' => $now->copy()->subHours(3),
                'last_attempt_at' => $now->copy()->subHours(1),
                'last_success_at' => null,
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 8) Auto-retry scheduled export — quality feedback.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450013010',
                'direction' => SapSyncDirection::EXPORT,
                'feedback_type' => SapFeedbackType::QUALITY,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo4?->id,
                'order_no' => $lo4?->order_no,
                'customer_id' => $lo4?->customer_id,
                'customer_name' => $lo4?->customer_name,
                'carrier_id' => $lo4?->carrier_id,
                'carrier_name' => $lo4?->carrier_name,
                'product_quality' => $lo4?->product_quality ?? 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::FAILED,
                'handling_status' => SapHandlingStatus::AUTO_RETRY_SCHEDULED,
                'issue_reason' => 'Quality result rejected by SAP validation; will retry once analysis is re-confirmed.',
                'what_happened' => 'Quality feedback could not be posted; backend will retry automatically.',
                'next_action' => 'Wait for automatic retry; no manual action required.',
                'technical_message' => "SAP returned status 'QA_VALIDATION_FAILED'.",
                'connector_message' => 'IDoc QPMK01 queued for retry.',
                'sync_run_id' => 'RUN-2026-0525-D',
                'interface_id' => 'IF-SAP-FEEDBACK-V1',
                'retry_count' => 1,
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subMinutes(45),
                'last_attempt_at' => $now->copy()->subMinutes(12),
                'last_success_at' => null,
                'attempts' => [
                    [
                        'attemptedAt' => $now->copy()->subMinutes(45)->toIso8601String(),
                        'resultStatus' => SapSyncResultStatus::FAILED,
                        'issueReason' => 'SAP returned QA_VALIDATION_FAILED.',
                        'handlingStatus' => SapHandlingStatus::AUTO_RETRY_SCHEDULED,
                    ],
                ],
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 9) Successful completion feedback export.
        SapSyncRecord::firstOrCreate(
            [
                'sap_reference' => 'SAP-450012970',
                'direction' => SapSyncDirection::EXPORT,
                'feedback_type' => SapFeedbackType::COMPLETION,
            ],
            [
                'id' => (string) Str::uuid(),
                'order_id' => $lo2?->id,
                'order_no' => $lo2?->order_no,
                'customer_id' => $lo2?->customer_id,
                'customer_name' => $lo2?->customer_name,
                'carrier_id' => $lo2?->carrier_id,
                'carrier_name' => $lo2?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'result_status' => SapSyncResultStatus::SUCCESS,
                'handling_status' => SapHandlingStatus::NO_ACTION_NEEDED,
                'issue_reason' => null,
                'what_happened' => 'Completion feedback sent to SAP and acknowledged.',
                'next_action' => 'No action — feedback delivered.',
                'sync_run_id' => 'RUN-2026-0524-Z',
                'interface_id' => 'IF-SAP-FEEDBACK-V1',
                'owner_role' => 'SAP_Responsible',
                'event_time' => $now->copy()->subDay()->subHours(1),
                'last_attempt_at' => $now->copy()->subDay()->subHours(1),
                'last_success_at' => $now->copy()->subDay()->subHours(1),
                'correlation_id' => (string) Str::uuid(),
            ]
        );
    }
}
