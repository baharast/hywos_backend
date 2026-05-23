<?php

namespace App\Models;

use App\Enums\LoadingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoadingOperation extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'display_no',
        'bay_line_id',
        'driver_id',
        'trailer_id',
        'trailer_label',
        'tractor_plate',
        'order_id',
        'order_no',
        'sap_order_no',
        'plant_visit_id',
        'visit_no',
        'customer_id',
        'customer_name',
        'product_quality',
        'target_quantity',
        'actual_quantity',
        'unit',
        'progress_percent',
        'loading_status',
        'analysis_status',
        'release_source',
        'release_reason_code',
        'release_reason',
        'has_clarification',
        'alarm_count',
        'critical_alarm_count',
        'started_at',
        'completed_at',
        'last_event_at',
        'plc_status',
        'correlation_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'target_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'progress_percent' => 'integer',
        'has_clarification' => 'boolean',
        'alarm_count' => 'integer',
        'critical_alarm_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->loading_status)) {
                $model->loading_status = LoadingStatus::ASSIGNED;
            }
            if (empty($model->analysis_status)) {
                $model->analysis_status = 'not_started';
            }
            if (empty($model->unit)) {
                $model->unit = 'kg';
            }
        });
    }

    public function bayLine(): BelongsTo
    {
        return $this->belongsTo(BayLine::class, 'bay_line_id', 'id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('loading_status', LoadingStatus::terminal());
    }

    /**
     * Derive an integer 0..100 progress from actual/target when the stored
     * percent column is missing. Returns null if neither path resolves.
     */
    public function getProgressAttribute(): ?int
    {
        if (! is_null($this->progress_percent)) {
            return (int) $this->progress_percent;
        }
        $target = (float) $this->target_quantity;
        $actual = (float) $this->actual_quantity;
        if ($target <= 0) {
            return null;
        }
        $pct = (int) floor(($actual / $target) * 100);
        return max(0, min(100, $pct));
    }
}
