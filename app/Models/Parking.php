<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Trailer Parking slot — MVP two-slot board.
 *
 * Per FillTrack Trailer Parking UX Spec V2.1 §0.2 the site has exactly two
 * slots (PARKING-1, PARKING-2). Trailer / order / visit / driver columns are
 * denormalized snapshots so the slot board can be rendered without joins.
 *
 * Controller endpoints land in TSK-006; this model exposes the schema, the
 * snapshot fields, and a few read-side helpers.
 */
class Parking extends Model
{
    use HasFactory;

    protected $table = 'parkings';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',

        'plant_configuration_id',
        'site_id',
        'plant_area_id',

        'slot_status',

        'current_trailer_id',
        'current_trailer_label',
        'current_trailer_plate',
        'current_trailer_chip',
        'current_load_state',

        'linked_order_id',
        'linked_order_no',
        'active_visit_id',
        'active_visit_no',
        'driver_id',
        'driver_name',

        'tractor_plate',

        'parked_since',
        'reserved_for',

        'blocker_reason',
        'clarification_case_id',

        'next_action',
        'document_summary',

        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'parked_since' => 'datetime',
        'reserved_for' => 'datetime',
        'document_summary' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Parking $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->slot_status)) {
                $model->slot_status = 'free';
            }
            if (empty($model->next_action)) {
                $model->next_action = 'none';
            }
        });
    }

    /* ----- Relationships ----- */

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class, 'current_trailer_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LoadingOrder::class, 'linked_order_id', 'id');
    }

    public function plantConfiguration(): BelongsTo
    {
        return $this->belongsTo(PlantConfiguration::class, 'plant_configuration_id', 'id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(PlantArea::class, 'plant_area_id', 'id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }

    /* ----- Scopes ----- */

    public function scopeFree(Builder $q): Builder
    {
        return $q->where('slot_status', 'free');
    }

    public function scopeOccupied(Builder $q): Builder
    {
        return $q->where('slot_status', 'occupied');
    }

    public function scopeNeedsAttention(Builder $q): Builder
    {
        return $q->whereIn('slot_status', ['blocked', 'out_of_service']);
    }

    /* ----- Derived accessors (do NOT persist) ----- */

    /**
     * V2.1 §6.4 — the Documents readiness block on a slot card is shown only
     * when a LOADED trailer is parked AND a linked order is known.
     */
    public function getShouldShowDocumentsAttribute(): bool
    {
        return $this->current_load_state === 'loaded'
            && ! empty($this->linked_order_id);
    }
}
