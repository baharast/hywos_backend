<?php

namespace App\Models;

use App\Enums\ActiveAnalysisStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * The Active Analysis JOB. Has many attempts; each attempt has its own
 * 6-element result rows.
 *
 * `required_action` / `allowed_actions` / `latest_message` /
 * `element_summary` are cached on the row by ActiveAnalysisService for
 * fast list queries — the service rewrites them on every state change.
 *
 * The `display_no` follows `AN-YYYY-NNNN` so it's recognisable in
 * timelines / event logs.
 */
class ActiveAnalysis extends Model
{
    use HasFactory;

    protected $table = 'analyses';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'display_no',
        'analysis_type',
        'sampling_trigger',
        'status',
        'order_id', 'order_no', 'sap_order_no',
        'plant_visit_id', 'visit_no',
        'loading_operation_id',
        'driver_id', 'driver_name',
        'trailer_id', 'trailer_label',
        'tractor_id', 'tractor_label',
        'bay_line_id', 'station_name',
        'device_id', 'device_bmk', 'device_name',
        'product_spec_id', 'product_code', 'spec_version',
        'attempt_count', 'max_attempts',
        'required_action', 'required_action_reason', 'allowed_actions',
        'latest_message', 'element_summary',
        'related_result_id', 'closed_at', 'closed_by_user_id',
        'held_at', 'held_by_user_id', 'hold_reason',
        'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason',
        'correlation_id', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'allowed_actions' => 'array',
        'closed_at' => 'datetime',
        'held_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActiveAnalysis $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = ActiveAnalysisStatus::QUEUED;
            }
            if (is_null($model->attempt_count)) {
                $model->attempt_count = 0;
            }
            if (is_null($model->max_attempts)) {
                $model->max_attempts = 3;
            }
            if (empty($model->display_no)) {
                $model->display_no = static::nextDisplayNo();
            }
        });
    }

    public static function nextDisplayNo(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', now()->year)->count();
        $seq = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
        return "AN-{$year}-{$seq}";
    }

    /* ----- Relations ----- */

    public function attempts(): HasMany
    {
        return $this->hasMany(AnalysisAttempt::class, 'analysis_id', 'id')->orderBy('attempt_no');
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(AnalysisAttempt::class, 'analysis_id', 'id')->latestOfMany('attempt_no');
    }

    public function elementResults(): HasMany
    {
        return $this->hasMany(AnalysisElementResult::class, 'analysis_id', 'id');
    }

    /* ----- Scopes ----- */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ActiveAnalysisStatus::openStatuses());
    }

    public function scopeActionRequired(Builder $q): Builder
    {
        return $q->whereNotNull('required_action');
    }

    public function scopeBlockingOps(Builder $q): Builder
    {
        // Approximate "blocking operations": NOK / INVALID / FAILED on
        // an analysis that hasn't been resolved yet.
        return $q->whereIn('status', [
            ActiveAnalysisStatus::INVALID,
            ActiveAnalysisStatus::NOK,
            ActiveAnalysisStatus::FAILED,
        ]);
    }
}
