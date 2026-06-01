<?php

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TanUsageState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot cleanup for auto-issued TANs created BEFORE the project
 * decision to surface the full 7-digit value through the API.
 *
 * The previous OrderProvisioningService revision stored the masked
 * form (`***-XXXX`) in `display_identifier` and left `identifier_value`
 * NULL. The kiosk + admin panel therefore showed e.g. `***-3768`,
 * which a driver cannot type at the bayline. Once the code-side fix
 * lands, NEW provisioning runs write the full raw value, but rows
 * already in the deployed DB stay masked — and we can't recover the
 * raw value from `identifier_hash` (SHA-256 is one-way).
 *
 * Fix: revoke the affected rows so the next workflow tick mints a
 * fresh, unmasked TAN.
 *
 * Match predicate (intentionally narrow):
 *   medium_type = 'tan'
 *   AND status = 'active'
 *   AND order_id IS NOT NULL                 ← auto-issued (not manual)
 *   AND display_identifier LIKE '***-%'      ← the masked form
 *   AND identifier_value IS NULL             ← raw value never stored
 *
 * Manual `POST /api/tans` rows are excluded by the order_id check.
 * Seeded TanSeeder rows are excluded by the LIKE — they store the
 * `XXX-XXXX` human form, not `***-XXXX`.
 *
 * Idempotent + reversible-safe:
 *   - up()   sets status=BLOCKED, usage_state=BLOCKED, stamps
 *            revoked_at + revocation_reason. Re-runs match zero rows.
 *   - down() is a no-op. Un-revoking would require restoring the lost
 *            raw value, which we can't, AND the affected sessions will
 *            already have fresh TANs by then.
 *
 * Operational effect on deployed envs after this migrates:
 *   - GET /api/terminal/session/{id}/bay-line-assignment returns
 *     fillingTan = null until the driver re-confirms the order at the
 *     kiosk (POST /api/terminal/session/{id}/order with task=filling).
 *   - confirmOrder mints a fresh filling TAN via the new
 *     provisionFillingTanFor flow, which now surfaces the full value.
 *   - LoadingOrderResource.tan (gate-entry) follows the same pattern:
 *     re-issuance happens on assign-driver (or on next provisionTanFor
 *     call); dispatcher can also revoke + regenerate manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auth_media')) {
            return;
        }

        DB::table('auth_media')
            ->where('medium_type', AuthMediumType::TAN)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->whereNotNull('order_id')
            ->whereNull('identifier_value')
            ->where('display_identifier', 'like', '***-%')
            ->update([
                'status' => AuthMediumStatus::BLOCKED,
                'usage_state' => TanUsageState::BLOCKED,
                'revoked_at' => now(),
                'revocation_reason' => 'Auto-revoked: TAN was issued with the legacy masked-only display. Re-issue via the workflow to surface the full 7-digit code.',
            ]);
    }

    public function down(): void
    {
        // No-op: see class doc. Reinstating these rows would only put
        // unusable masked TANs back into the wire payload.
    }
};
