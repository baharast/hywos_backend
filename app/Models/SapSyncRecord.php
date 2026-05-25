<?php

namespace App\Models;

use App\Enums\SapHandlingStatus;
use App\Enums\SapSyncDirection;
use App\Enums\SapSyncResultStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One order-related SAP communication event (V1.5).
 *
 * Read-only in MVP. Rows are produced by SAP connectors / backend jobs; the
 * dashboard never writes them. Future write endpoints (manual retry) are
 * deliberately out of scope per V1.5 §2.2.
 *
 * Snapshot columns (`order_no`, `customer_name`, `carrier_name`, ...) are
 * captured at sync time so the diagnostic view stays meaningful even after
 * the referenced order/customer/carrier is renamed or deleted.
 */
class SapSyncRecord extends Model
{
    use HasFactory;

    protected $table = 'sap_sync_records';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'direction',
        'sap_reference',
        'order_id',
        'order_no',
        'customer_id',
        'customer_name',
        'carrier_id',
        'carrier_name',
        'product_quality',
        'result_status',
        'handling_status',
        'feedback_type',
        'issue_reason',
        'what_happened',
        'next_action',
        'technical_message',
        'connector_message',
        'sync_run_id',
        'interface_id',
        'retry_count',
        'owner_role',
        'event_time',
        'last_attempt_at',
        'last_success_at',
        'attempts',
        'correlation_id',
        'notes',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'last_attempt_at' => 'datetime',
        'last_success_at' => 'datetime',
        'attempts' => 'array',
        'retry_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SapSyncRecord $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /* ----- Relationships (soft FKs) ----- */

    public function order(): BelongsTo
    {
        return $this->belongsTo(LoadingOrder::class, 'order_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(FreightForwarder::class, 'carrier_id', 'id');
    }

    /* ----- Scopes ----- */

    public function scopeImports(Builder $q): Builder
    {
        return $q->where('direction', SapSyncDirection::IMPORT);
    }

    public function scopeExports(Builder $q): Builder
    {
        return $q->where('direction', SapSyncDirection::EXPORT);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('result_status', SapSyncResultStatus::FAILED);
    }

    public function scopeNeedsSupport(Builder $q): Builder
    {
        return $q->where('handling_status', SapHandlingStatus::NEEDS_SUPPORT);
    }
}
