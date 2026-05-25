<?php

namespace App\Models;

use App\Services\LoadingOrders\LoadingOrderReadinessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoadingOrder extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * SAP-owned fields are locked from local edits when `is_sap_owned=true`,
     * mirroring the Customer / Carrier SAP-protection pattern.
     */
    public const SAP_OWNED_FIELDS = [
        'order_no',
        'sap_reference',
        'customer_id',
        'carrier_id',
        'product_quality',
        'target_quantity',
        'unit',
    ];

    protected $fillable = [
        'id',
        'order_no',
        'source',
        'sap_reference',
        'external_reference',
        'customer_id',
        'customer_name',
        'carrier_id',
        'carrier_name',
        'product_quality',
        'target_quantity',
        'unit',
        'planned_window_start',
        'planned_window_end',
        'assigned_driver_id',
        'assigned_driver_name',
        'assigned_driver_code',
        'assigned_trailer_id',
        'assigned_trailer_label',
        'assigned_trailer_plate',
        'task_flow',
        'requires_certificate',
        'requires_delivery_note',
        'requires_qm_document',
        'status',
        'current_step',
        'blocking_reason',
        'blocking_reason_code',
        'blocked_at',
        'blocked_by_user_id',
        'cancellation_reason',
        'cancellation_reason_code',
        'cancelled_at',
        'cancelled_by_user_id',
        'is_locked_by_execution',
        'active_plant_visit_id',
        'active_plant_visit_no',
        'active_loading_operation_id',
        'is_sap_owned',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'target_quantity' => 'decimal:3',
        'planned_window_start' => 'datetime',
        'planned_window_end' => 'datetime',
        'requires_certificate' => 'boolean',
        'requires_delivery_note' => 'boolean',
        'requires_qm_document' => 'boolean',
        'blocked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_locked_by_execution' => 'boolean',
        'is_sap_owned' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (LoadingOrder $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->source)) {
                $model->source = 'manual';
            }
        });

        // Keep the persisted `status` snapshot in sync with the derived value
        // on every write. Controllers may still override BLOCKED / CANCELLED
        // by toggling the corresponding *_at timestamps; the readiness
        // service honors those.
        static::saving(function (LoadingOrder $model) {
            app(LoadingOrderReadinessService::class)->refresh($model);
        });
    }

    /* ----- Relationships ----- */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function carrier(): BelongsTo
    {
        // freight_forwarders is the canonical carrier table; soft FK kept nullable.
        return $this->belongsTo(FreightForwarder::class, 'carrier_id', 'id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id', 'id');
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class, 'assigned_trailer_id', 'id');
    }

    /* ----- Scopes ----- */

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeNeedsAssignment(Builder $q): Builder
    {
        return $q->where('status', 'needs_assignment');
    }

    public function scopeOpenForExecution(Builder $q): Builder
    {
        return $q->whereIn('status', ['ready', 'in_progress']);
    }

    /* ----- Derived accessors (do NOT persist) ----- */

    public function getDriverAssignmentStateAttribute(): string
    {
        return app(LoadingOrderReadinessService::class)->driverAssignmentState($this);
    }

    public function getTrailerAssignmentStateAttribute(): string
    {
        return app(LoadingOrderReadinessService::class)->trailerAssignmentState($this);
    }
}
