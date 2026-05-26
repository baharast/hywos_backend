<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One row per (profile, gas component). Carries the exact reference
 * value + acceptance tolerance the calibration run is compared against.
 *
 * `last_measured_value`, `last_deviation`, `last_deviation_percent`,
 * `last_result` and `last_run_at` are READ-ONLY for the user (V2.1 §5.7
 * — must NOT appear as editable form fields). They are set by the
 * calibration run / device interface and surfaced in the resource layer
 * with read-only flags.
 */
class CalibrationComponent extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'profile_id',
        'component',
        'unit',
        'exact_value',
        'tolerance_abs',
        'tolerance_percent',
        'precision_decimals',
        'rounding_rule',
        'last_measured_value',
        'last_deviation',
        'last_deviation_percent',
        'last_result',
        'last_run_at',
        'last_change_reason',
        'updated_by_user_id',
    ];

    protected $casts = [
        'exact_value' => 'decimal:6',
        'tolerance_abs' => 'decimal:6',
        'tolerance_percent' => 'decimal:2',
        'precision_decimals' => 'integer',
        'last_measured_value' => 'decimal:6',
        'last_deviation' => 'decimal:6',
        'last_deviation_percent' => 'decimal:3',
        'last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CalibrationComponent $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CalibrationProfile::class, 'profile_id', 'id');
    }
}
