<?php

namespace App\Models;

use App\Enums\HardwareDeviceHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Master registry row for FillTrack System & Devices V1.4.
 *
 * `is_blocking_critical_process` is computed in `saving()` so the column
 * stays consistent with (criticality, health, service_mode) without a
 * background job. This is the cache the V1.4 §9 default sort relies on
 * to push fault/critical rows to the top.
 */
class HardwareDevice extends Model
{
    use HasFactory;

    protected $table = 'hardware_devices';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'asset_tag', 'vendor_tag', 'name',
        'device_type', 'subsystem', 'physical_location',
        'health', 'criticality',
        'affected_process', 'affected_process_label',
        'protocol',
        'last_seen_at', 'last_event_at', 'last_message',
        'service_mode', 'service_mode_reason', 'service_mode_set_at',
        'service_mode_set_by_user_id', 'service_mode_expected_end_at',
        'connection_test_last_run_at', 'connection_test_last_result',
        'is_blocking_critical_process',
        'data_status', 'source_basis', 'correlation_id',
    ];

    protected $casts = [
        'service_mode' => 'boolean',
        'is_blocking_critical_process' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_event_at' => 'datetime',
        'service_mode_set_at' => 'datetime',
        'service_mode_expected_end_at' => 'datetime',
        'connection_test_last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (HardwareDevice $m): void {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
        });

        static::saving(function (HardwareDevice $m): void {
            // V1.4 §4.2 summary bar + §9 default sort rely on this flag
            // being correct on every read. We recompute it from the
            // tuple (criticality, health, service_mode) on each save so
            // the column never drifts.
            $m->is_blocking_critical_process = $m->shouldFlagBlocking();
        });
    }

    /**
     * The denormalised "blocks a critical process" rule.
     *
     * True when this is a CRITICAL device that is currently UNHEALTHY
     * and NOT deliberately placed in service mode. A device intentionally
     * taken out of service does not count as a blocker for the summary
     * headline — it's already audit-tracked separately (§4.2 Service Mode
     * Active counter).
     */
    public function shouldFlagBlocking(): bool
    {
        if ($this->service_mode) {
            return false;
        }
        if ($this->criticality !== 'critical') {
            return false;
        }
        return in_array($this->health, [
            HardwareDeviceHealth::ALARM,
            HardwareDeviceHealth::FAULT,
            HardwareDeviceHealth::OFFLINE,
        ], true);
    }

    /* ----- Scopes ----- */

    public function scopeAbnormal(Builder $q): Builder
    {
        return $q->whereIn('health', [
            HardwareDeviceHealth::WARNING,
            HardwareDeviceHealth::ALARM,
            HardwareDeviceHealth::FAULT,
            HardwareDeviceHealth::OFFLINE,
            HardwareDeviceHealth::UNKNOWN,
        ]);
    }

    public function scopeServiceMode(Builder $q): Builder
    {
        return $q->where('service_mode', true);
    }

    public function scopeBlockingCritical(Builder $q): Builder
    {
        return $q->where('is_blocking_critical_process', true);
    }
}
