<?php

namespace App\Models;

use App\Enums\InterfaceBlockingLevel;
use App\Enums\InterfaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * V1.4 §9 — one row per "exact interface" (SAP inbound order import,
 * OPC UA plant link, printer service, ...). The model is named
 * `InterfaceHealth` because `Interface` is reserved by PHP; the table
 * stays `interfaces` so SQL reads naturally.
 *
 * Read-mostly: the only write surface is `last_retry_*` from the
 * controller's `requestRetry` action. No CRUD endpoints; rows come from
 * the seeder.
 */
class InterfaceHealth extends Model
{
    use HasFactory;

    protected $table = 'interfaces';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'exact_interface_id',
        'name',
        'family',
        'protocol',
        'direction',
        'source_label',
        'target_label',
        'status',
        'blocking_level',
        'last_success_at',
        'last_failure_at',
        'queue_count',
        'failed_today',
        'last_error_text',
        'last_retry_at',
        'last_retry_by_user_id',
        'fallback_behavior',
        'local_operation_allowed',
        'affected_process_label',
        'data_status',
        'source_basis',
        'correlation_id',
    ];

    protected $casts = [
        'local_operation_allowed' => 'boolean',
        'queue_count' => 'integer',
        'failed_today' => 'integer',
        'last_retry_by_user_id' => 'integer',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InterfaceHealth $row): void {
            if (empty($row->{$row->getKeyName()})) {
                $row->{$row->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /* ---------- scopes ---------- */

    /**
     * Anything not currently healthy. The opposite of `where status =
     * online`, but also excludes `disabled` (deliberately stopped — not
     * actually abnormal).
     */
    public function scopeAbnormal(Builder $q): Builder
    {
        return $q->whereIn('status', [
            InterfaceStatus::WARNING,
            InterfaceStatus::FAULT,
            InterfaceStatus::OFFLINE,
            InterfaceStatus::UNKNOWN,
        ]);
    }

    /**
     * "Critical problem" = fault or offline AND blocking_level=critical.
     * Drives the headline counter on the page summary.
     */
    public function scopeCriticalIssues(Builder $q): Builder
    {
        return $q
            ->whereIn('status', [InterfaceStatus::FAULT, InterfaceStatus::OFFLINE])
            ->where('blocking_level', InterfaceBlockingLevel::CRITICAL);
    }

    public function scopeForFamily(Builder $q, string $family): Builder
    {
        return $q->where('family', $family);
    }
}
