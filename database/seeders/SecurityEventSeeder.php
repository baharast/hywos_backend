<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Models\Driver;
use App\Models\EventLog;
use Illuminate\Database\Seeder;

/**
 * Demo seed for V1 §7.6 Security Events.
 *
 * Writes into the existing `event_logs` table with
 * `event_category='security'` — the SecurityEventsService filters
 * exclusively on that column. Each row exercises a different
 * (category, result) pair so the FE filter card has real data for
 * every dropdown entry. risk + securityCategory + result are DERIVED
 * at read time from event_type + severity, so the seeder only needs
 * to set those two for the derivation to land correctly.
 *
 *   Type prefix → SecurityEventCategory
 *     auth.*           → authentication
 *     permission.*     → authorization
 *     user.*           → account_lifecycle (or role_permission if tail contains 'role')
 *     role.*           → role_permission
 *     auth_medium.* /
 *     tan.* / chip_card.* → chip_tan_access
 *     session.*        → session
 *     api.*            → api_integration
 *
 *   Type tail → SecurityResult
 *     locked / expired / blocked / denied / failed / success
 *
 *   Severity → Risk fallback
 *     critical → critical | danger → high | warning → medium | info → info/low
 */
class SecurityEventSeeder extends Seeder
{
    public function run(): void
    {
        $drvBlocked = Driver::query()->where('driver_code', 'DRV-1005')->first();
        $drvOk = Driver::query()->where('driver_code', 'DRV-1001')->first();

        $rows = [
            // 1) AUTHENTICATION — failed login (high risk)
            [
                'event_type' => 'auth.login_failed',
                'severity' => EventSeverity::DANGER,
                'actor_name' => 'unknown@dispatcher-pc-2',
                'message' => 'Failed login attempt for username "operations" — invalid password.',
                'details' => [
                    'attempt_count_last_hour' => 4,
                    'username_attempted' => 'operations',
                    'source' => 'dispatcher-pc-2',
                ],
                'ip' => '10.42.18.71',
                'offset' => '-22 minutes',
            ],
            // 2) AUTHENTICATION — account locked (critical risk)
            [
                'event_type' => 'auth.account_locked',
                'severity' => EventSeverity::CRITICAL,
                'actor_name' => 'lockeduser',
                'message' => 'Account locked after 5 consecutive failed login attempts.',
                'details' => [
                    'username' => 'lockeduser',
                    'lock_duration_minutes' => 30,
                    'auto_unlock_at' => null,
                ],
                'ip' => '10.42.18.71',
                'offset' => '-18 minutes',
                'entity_type' => 'App\\Models\\User',
                'entity_id' => '0',
            ],
            // 3) AUTHENTICATION — successful login (info risk)
            [
                'event_type' => 'auth.login_succeeded',
                'severity' => EventSeverity::INFO,
                'actor_name' => 'admin',
                'message' => 'Manager logon issued Sanctum token (TTL 24h).',
                'details' => [
                    'username' => 'admin',
                    'token_ttl_hours' => 24,
                ],
                'ip' => '10.42.10.5',
                'offset' => '-2 hours',
            ],
            // 4) AUTHORIZATION — permission denied (medium risk)
            [
                'event_type' => 'permission.denied',
                'severity' => EventSeverity::WARNING,
                'actor_name' => 'operator',
                'message' => 'User "operator" attempted to access /api/users without users.view permission.',
                'details' => [
                    'username' => 'operator',
                    'required_permission' => 'users.view',
                    'route' => 'GET /api/users',
                ],
                'ip' => '10.42.11.18',
                'offset' => '-45 minutes',
            ],
            // 5) ACCOUNT LIFECYCLE — user disabled
            [
                'event_type' => 'user.disabled',
                'severity' => EventSeverity::WARNING,
                'actor_name' => 'admin',
                'message' => 'Account "disableduser" disabled by admin.',
                'details' => [
                    'target_username' => 'disableduser',
                    'reason' => 'Long-term leave',
                ],
                'ip' => '10.42.10.5',
                'offset' => '-4 hours',
            ],
            // 6) ROLE / PERMISSION — role assignment changed
            [
                'event_type' => 'role.permissions_updated',
                'severity' => EventSeverity::WARNING,
                'actor_name' => 'admin',
                'message' => 'Role "operator" granted alarms.acknowledge.',
                'details' => [
                    'role' => 'operator',
                    'added' => ['alarms.acknowledge'],
                    'removed' => [],
                ],
                'ip' => '10.42.10.5',
                'offset' => '-3 hours',
            ],
            // 7) CHIP/TAN ACCESS — TAN expired at gate (medium risk)
            [
                'event_type' => 'tan.expired_at_gate',
                'severity' => EventSeverity::WARNING,
                'actor_name' => $drvOk?->driver_code,
                'message' => 'Driver presented an expired TAN at the entry gate.',
                'details' => [
                    'driver_code' => $drvOk?->driver_code,
                    'masked_tan' => '***-9173',
                    'gate' => 'HD-GATE-ENTRY-01',
                ],
                'ip' => null,
                'offset' => '-95 minutes',
                'entity_type' => $drvOk ? Driver::class : null,
                'entity_id' => $drvOk?->id,
            ],
            // 8) CHIP/TAN ACCESS — driver blocked at terminal (high risk)
            [
                'event_type' => 'auth_medium.driver_blocked',
                'severity' => EventSeverity::DANGER,
                'actor_name' => $drvBlocked?->driver_code,
                'message' => 'Blocked driver presented a chip at the driver terminal.',
                'details' => [
                    'driver_code' => $drvBlocked?->driver_code,
                    'masked_chip' => 'CHIP-1005',
                    'terminal' => 'TERM-DRV-1',
                ],
                'ip' => null,
                'offset' => '-50 minutes',
                'entity_type' => $drvBlocked ? Driver::class : null,
                'entity_id' => $drvBlocked?->id,
            ],
            // 9) CHIP/TAN ACCESS — chip blocked (high risk)
            [
                'event_type' => 'chip_card.blocked_presented',
                'severity' => EventSeverity::DANGER,
                'actor_name' => 'unknown',
                'message' => 'Card CC-0004 (status=blocked) presented at entry gate.',
                'details' => [
                    'card_code' => 'CC-0004',
                    'gate' => 'HD-GATE-ENTRY-01',
                ],
                'ip' => null,
                'offset' => '-100 minutes',
            ],
            // 10) SESSION — session timeout
            [
                'event_type' => 'session.timeout',
                'severity' => EventSeverity::INFO,
                'actor_name' => 'DRV-1001',
                'message' => 'Driver session auto-logged out after 5 min inactivity.',
                'details' => [
                    'session_no' => 'SES-2026-0033',
                    'reason' => 'timeout',
                ],
                'ip' => null,
                'offset' => '-30 minutes',
            ],
            // 11) API / INTEGRATION — service token revoked
            [
                'event_type' => 'api.token_revoked',
                'severity' => EventSeverity::WARNING,
                'actor_name' => 'admin',
                'message' => 'Long-lived service token for SAP connector revoked and rotated.',
                'details' => [
                    'service' => 'sap-connector',
                    'reason' => 'scheduled rotation',
                ],
                'ip' => '10.42.10.5',
                'offset' => '-6 hours',
            ],
            // 12) AUTHENTICATION — repeated failed attempts (high risk)
            [
                'event_type' => 'auth.login_failed',
                'severity' => EventSeverity::DANGER,
                'actor_name' => 'unknown@unknown',
                'message' => 'Failed login: 5 attempts on username "admin" from the same IP in 10 min.',
                'details' => [
                    'username_attempted' => 'admin',
                    'attempt_count_window_10m' => 5,
                    'note' => 'Pattern suggests password spray; alert IT/Security.',
                ],
                'ip' => '203.0.113.42',
                'offset' => '-10 minutes',
            ],
        ];

        foreach ($rows as $r) {
            // Idempotent: keyed by (event_type, offset → occurred_at)
            // so re-running the seeder doesn't duplicate but updates the
            // matching row. We bucket on event_type + correlation id so
            // similar rows from a real flow (e.g. multiple failed-login
            // attempts) coexist.
            $occurredAt = now()->modify($r['offset']);
            $correlationId = 'seed-' . substr(sha1($r['event_type'] . $r['offset']), 0, 12);

            EventLog::query()->updateOrCreate(
                ['correlation_id' => $correlationId, 'event_type' => $r['event_type']],
                [
                    'event_category' => EventCategory::SECURITY,
                    'severity' => $r['severity'],
                    'actor_name' => $r['actor_name'],
                    'entity_type' => $r['entity_type'] ?? null,
                    'entity_id' => $r['entity_id'] ?? null,
                    'message' => $r['message'],
                    'details' => $r['details'] ?? null,
                    'ip_address' => $r['ip'],
                    'occurred_at' => $occurredAt,
                ]
            );
        }
    }
}
