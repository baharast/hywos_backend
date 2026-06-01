<?php

namespace App\Services\LoadingOrders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TanPurpose;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use App\Models\BayLine;
use App\Models\Driver;
use App\Models\LoadingOrder;
use App\Services\Tans\TanPolicy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Side-effect helper for loading order creation + driver assignment.
 *
 * Spec rule (operations note from chat): every order that has a driver
 * must also have a TAN the driver can use to enter at the gate, and a
 * bay line so the management list shows the planned bay. Both are
 * best-effort — failure logs a warning but never blocks order creation.
 *
 * The TAN is single-use, open-ended (no expires_at — matches the new
 * seeder convention), and linked to BOTH the driver AND the order via
 * auth_media.order_id so the FE can `whereOrderId(...)` on retrieval.
 *
 * Bay-line picker is intentionally naive in V1: first `free + active`
 * line whose `allowed_product` matches the order's `product_quality`
 * (or any active free line if product is unset). Returns null when
 * nothing fits — list endpoint then shows the order with no bay yet.
 */
class OrderProvisioningService
{
    /**
     * Ensure the order has an active TAN linked to the assigned driver.
     *
     * Idempotent: if an active TAN already exists for this order, it's
     * returned unchanged. If the order's assigned driver changed, the
     * previous TAN is revoked and a new one is provisioned for the new
     * driver — so the management list never shows a stale TAN.
     */
    public function provisionTanFor(LoadingOrder $order, ?Driver $driver = null): ?AuthMedium
    {
        if ($driver === null && $order->assigned_driver_id !== null) {
            $driver = Driver::find($order->assigned_driver_id);
        }
        if ($driver === null) {
            return null;
        }

        try {
            $existing = AuthMedium::query()
                ->where('medium_type', AuthMediumType::TAN)
                ->where('order_id', $order->id)
                ->where('tan_purpose', TanPurpose::GATE_ENTRY)
                ->where('status', AuthMediumStatus::ACTIVE)
                ->first();

            // Same driver still owns the TAN → keep it.
            if ($existing !== null && $existing->driver_id === $driver->id) {
                return $existing;
            }

            // Different driver (or unassigned) → retire the stale one
            // before issuing a new one. We mark it BLOCKED rather than
            // REVOKED because the order itself is still alive; this
            // mirrors the V3 §5.1 "reassigned" hint without inventing
            // a new status.
            if ($existing !== null) {
                $existing->update([
                    'status' => AuthMediumStatus::BLOCKED,
                    'usage_state' => TanUsageState::BLOCKED,
                    'revoked_at' => now(),
                    'revocation_reason' => 'Order reassigned to a different driver',
                ]);
            }

            [$rawValue, $hash, $masked] = $this->generateTanCredential();

            return AuthMedium::create([
                'id' => (string) Str::uuid(),
                'medium_type' => AuthMediumType::TAN,
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                // Project decision (2026-05-31): persist BOTH the raw
                // value AND the masked form so the driver-facing kiosk
                // + the dispatcher panel can show the actual 7-digit
                // code through TanResource / fillingTan / tan blocks
                // without each surface having to special-case the
                // reveal. identifier_hash stays for the login lookup
                // path which still hashes inputs before matching.
                'identifier_value' => $rawValue,
                'identifier_hash' => $hash,
                'display_identifier' => $rawValue,
                'tan_masked' => $masked,
                'tan_reference' => $this->nextTanReference('TAN'),
                'tan_purpose' => TanPurpose::GATE_ENTRY,
                'is_single_use' => true,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'consumption_count' => 0,
                'issued_at' => now(),
                'valid_from' => now(),
                'expires_at' => null,
                'reason' => "Auto-issued for order {$order->order_no}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('OrderProvisioningService::provisionTanFor failed', [
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Issue (or fetch the existing) filling-station TAN for an order
     * the driver has just confirmed at the terminal.
     *
     * Distinct from the gate-entry TAN: this one is shown on the
     * terminal kiosk as "use this TAN at bayline X". The reference
     * uses an `FT-` prefix so the driver can tell the two TANs apart
     * at a glance.
     *
     * Idempotent for the same (order, driver) tuple — re-confirming
     * the order does NOT mint a new TAN. If the driver changed since
     * the last filling TAN was issued, the old one is BLOCKED and a
     * new one minted (same rotation rule as the entry TAN).
     */
    public function provisionFillingTanFor(LoadingOrder $order, ?Driver $driver = null): ?AuthMedium
    {
        if ($driver === null && $order->assigned_driver_id !== null) {
            $driver = Driver::find($order->assigned_driver_id);
        }
        if ($driver === null) {
            return null;
        }

        try {
            $existing = AuthMedium::query()
                ->where('medium_type', AuthMediumType::TAN)
                ->where('order_id', $order->id)
                ->where('tan_purpose', TanPurpose::FILLING)
                ->where('status', AuthMediumStatus::ACTIVE)
                ->first();

            if ($existing !== null && $existing->driver_id === $driver->id) {
                return $existing;
            }

            if ($existing !== null) {
                $existing->update([
                    'status' => AuthMediumStatus::BLOCKED,
                    'usage_state' => TanUsageState::BLOCKED,
                    'revoked_at' => now(),
                    'revocation_reason' => 'Order reassigned to a different driver',
                ]);
            }

            [$rawValue, $hash, $masked] = $this->generateTanCredential();

            return AuthMedium::create([
                'id' => (string) Str::uuid(),
                'medium_type' => AuthMediumType::TAN,
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                // Project decision (2026-05-31): persist BOTH the raw
                // value AND the masked form — driver needs to type the
                // actual 7-digit code at the bayline panel, so the
                // kiosk surfaces the full value via display_identifier.
                // See generateTanCredential() docblock for the rationale.
                'identifier_value' => $rawValue,
                'identifier_hash' => $hash,
                'display_identifier' => $rawValue,
                'tan_masked' => $masked,
                'tan_reference' => $this->nextTanReference('FT'),
                'tan_purpose' => TanPurpose::FILLING,
                'is_single_use' => true,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'consumption_count' => 0,
                'issued_at' => now(),
                'valid_from' => now(),
                'expires_at' => null,
                'reason' => "Auto-issued for filling station, order {$order->order_no}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('OrderProvisioningService::provisionFillingTanFor failed', [
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Assign the first available bay line to the order if none is
     * already set. Best-effort — returns the assigned BayLine, or null
     * when no candidate matches.
     *
     * Selection rule:
     *   1. `is_active = true`
     *   2. `status_code = 'free'`
     *   3. `allowed_product` matches the order's `product_quality`
     *      when product_quality is set. If unset, any allowed product
     *      qualifies.
     *   4. Stable ordering by `code` so demo data is deterministic.
     *
     * Bay-line state is left at `free` — actual reservation happens
     * later in the Loading Control flow. This service only records
     * the planned bay on the order so the FE list can show it.
     */
    public function provisionBayLineFor(LoadingOrder $order): ?BayLine
    {
        if (! empty($order->assigned_bay_line_id)) {
            return BayLine::query()->find($order->assigned_bay_line_id);
        }

        try {
            $query = BayLine::query()
                ->where('is_active', true)
                ->where('status_code', 'free');

            if (! empty($order->product_quality)) {
                $query->where(function ($q) use ($order) {
                    $q->where('allowed_product', $order->product_quality)
                        ->orWhereNull('allowed_product');
                });
            }

            $bayLine = $query->orderBy('code')->first();
            if ($bayLine === null) {
                return null;
            }

            $order->forceFill([
                'assigned_bay_line_id' => $bayLine->id,
                'assigned_bay_line_code' => $bayLine->code,
                'assigned_bay_line_name' => $bayLine->name,
            ])->save();

            return $bayLine;
        } catch (\Throwable $e) {
            Log::warning('OrderProvisioningService::provisionBayLineFor failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * V6 §16.1 TAN credential generator. Returns the
     * (raw, hash, masked) triple shared by both gate-entry and filling
     * paths.
     *
     * Generates a 7-digit value via CSPRNG (random_int min/max derived
     * from TanPolicy::VALUE_DIGITS), then:
     *   - hashes it with SHA-256 → identifier_hash (persisted)
     *   - masks the last 4 digits as "***-XXXX" → tan_masked
     *   - returns the FULL raw value so the caller can persist it in
     *     identifier_value + display_identifier
     *
     * Project decision (2026-05-31, mirrors the manual TanController
     * full-value reveal): auto-issued TANs persist the raw value so
     * the kiosk + admin panel both display the actual code instead of
     * the masked form. The driver needs to read 7 digits at the
     * bayline; showing them "***-3768" is useless. identifier_hash
     * stays alongside for the login-path lookup (TerminalAuthService
     * still hashes inputs before matching).
     *
     * @return array{0:string,1:string,2:string} [raw, hash, masked]
     */
    protected function generateTanCredential(): array
    {
        $min = (int) str_pad('1', TanPolicy::VALUE_DIGITS, '0');
        $max = (int) str_repeat('9', TanPolicy::VALUE_DIGITS);
        $rawValue = (string) random_int($min, $max);
        $hash = hash('sha256', $rawValue);
        $masked = '***-' . substr($rawValue, -4);
        return [$rawValue, $hash, $masked];
    }

    /**
     * Generates the next TAN reference for the given prefix family.
     *   TAN-YYYY-NNNN   gate-entry credential
     *   FT-YYYY-NNNN    filling-station credential
     *
     * Uses a max+1 pickup on `tan_reference` for the matching prefix
     * so the two sequences advance independently and gaps in older
     * numbers don't reset the counter.
     */
    protected function nextTanReference(string $family = 'TAN'): string
    {
        $year = (int) now()->format('Y');
        $prefix = "{$family}-{$year}-";

        $lastNo = AuthMedium::query()
            ->where('tan_reference', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(tan_reference, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS max_no")
            ->value('max_no');

        $next = ((int) ($lastNo ?? 0)) + 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
