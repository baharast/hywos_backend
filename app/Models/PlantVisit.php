<?php

namespace App\Models;

use App\Enums\PlantVisitStatus;
use App\Services\PlantVisits\PlantVisitStepValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Plant Visit aggregate.
 *
 * One row per driver/vehicle/trailer presence at the plant, from entry to
 * exit. Per Active Plant Visits V1.6 §2 the THREE workflow concepts
 * (task_flow / current_step / visit_status) are deliberately kept as
 * separate fields — controllers and resources must NEVER collapse them
 * into a single status badge.
 *
 * Snapshot columns (driver/tractor/trailer/order_snapshot) are intended to
 * be immutable after the visit is first created (V1.6 §15 "Snapshot
 * protection"). The saving() hook will silently restore the original
 * snapshot if it is mutated after creation, and emit a Log::warning so
 * the regression is visible — we do NOT throw, per the spec's
 * "do not silently display invalid state" rule applied symmetrically.
 *
 * The saving() hook ALSO runs PlantVisitStepValidator::ensureConsistent()
 * which rewrites visit_status to CLARIFICATION + blocker_reason=
 * 'step_mismatch' when (task_flow, current_step) is invalid — instead of
 * throwing, instead of silently saving an invalid combo.
 */
class PlantVisit extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'visit_no',
        'driver_id',
        'driver_name',
        'driver_code',
        'tractor_vehicle_id',
        'tractor_plate',
        'trailer_id',
        'trailer_label',
        'trailer_plate',
        'trailer_chip',
        'order_id',
        'order_no',
        'sap_reference',
        'task_flow',
        'current_step',
        'visit_status',
        'current_location',
        'next_action_label',
        'next_action_target',
        'waiting_reason',
        'blocker_reason',
        'owner_role',
        'clarification_case_id',
        'entry_time',
        'exit_time',
        'closed_at',
        'closed_by_user_id',
        'closure_reason',
        'driver_snapshot',
        'tractor_snapshot',
        'trailer_snapshot',
        'order_snapshot',
        'correlation_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'closed_at' => 'datetime',
        'driver_snapshot' => 'array',
        'tractor_snapshot' => 'array',
        'trailer_snapshot' => 'array',
        'order_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PlantVisit $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->visit_status)) {
                $model->visit_status = PlantVisitStatus::NORMAL;
            }
            if (empty($model->entry_time)) {
                $model->entry_time = now();
            }
        });

        static::saving(function (PlantVisit $model) {
            // V1.6 §7 — visit_status='waiting' is meaningless without a
            // visible reason. Demote to NORMAL and log so it surfaces in
            // ops, rather than throwing (which would block clean writes).
            if ($model->visit_status === PlantVisitStatus::WAITING && empty($model->waiting_reason)) {
                Log::warning('PlantVisit saved with status=waiting but no waiting_reason; demoting to normal.', [
                    'visit_id' => $model->id,
                    'visit_no' => $model->visit_no,
                ]);
                $model->visit_status = PlantVisitStatus::NORMAL;
            }

            // V1.6 §7.1 — (task_flow, current_step) consistency rewrite.
            app(PlantVisitStepValidator::class)->ensureConsistent($model);

            // V1.6 §15 — snapshot immutability after first set. We only
            // enforce on existing rows (not creating), and only when the
            // attribute was actually changed.
            if ($model->exists) {
                foreach (['driver_snapshot', 'tractor_snapshot', 'trailer_snapshot', 'order_snapshot'] as $snapshotKey) {
                    if (! $model->isDirty($snapshotKey)) {
                        continue;
                    }
                    $original = $model->getOriginal($snapshotKey);
                    // If original was already populated, refuse the mutation.
                    if (! empty($original)) {
                        Log::warning("PlantVisit attempt to mutate immutable snapshot column [{$snapshotKey}]; restoring original.", [
                            'visit_id' => $model->id,
                            'visit_no' => $model->visit_no,
                        ]);
                        $model->setRawAttributes(
                            array_merge($model->getAttributes(), [$snapshotKey => $original])
                        );
                    }
                }
            }
        });
    }

    /* ----- Relationships (soft FKs — null tolerated) ----- */

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function tractorVehicle(): BelongsTo
    {
        return $this->belongsTo(TractorVehicle::class, 'tractor_vehicle_id', 'id');
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class, 'trailer_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LoadingOrder::class, 'order_id', 'id');
    }

    /* ----- Scopes ----- */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('visit_status', '!=', PlantVisitStatus::CLOSED);
    }

    public function scopeNeedsAction(Builder $q): Builder
    {
        return $q->whereIn('visit_status', [
            PlantVisitStatus::BLOCKED,
            PlantVisitStatus::CLARIFICATION,
        ]);
    }

    public function scopeForOrder(Builder $q, string $orderId): Builder
    {
        return $q->where('order_id', $orderId);
    }

    public function scopeInLocation(Builder $q, string $location): Builder
    {
        return $q->where('current_location', $location);
    }

    /* ----- Accessors ----- */

    public function getIsOpenAttribute(): bool
    {
        return $this->visit_status !== PlantVisitStatus::CLOSED;
    }
}
