<?php

namespace App\Models;

use App\Enums\CalibrationProfileStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Calibration profile header. `device_id` is a SOFT FK to analysis_devices
 * (may not exist yet); `device_bmk` + `device_name` are snapshot columns
 * so the profile stays readable if the device row is later renamed.
 *
 * `status` is the lifecycle (draft / active / inactive / retired).
 * `calibration_status` is the operational health (valid / due_soon /
 * overdue / failed / not_configured). They must NOT be conflated.
 */
class CalibrationProfile extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_id',
        'device_bmk',
        'device_name',
        'calibration_medium',
        'certificate_ref',
        'profile_version',
        'status',
        'calibration_status',
        'lockout_behavior',
        'medium_expiry_at',
        'next_due_at',
        'last_run_at',
        'notes',
        'activated_at',
        'activated_by_user_id',
        'retired_at',
        'retired_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'correlation_id',
    ];

    protected $casts = [
        'medium_expiry_at' => 'datetime',
        'next_due_at' => 'datetime',
        'last_run_at' => 'datetime',
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CalibrationProfile $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = CalibrationProfileStatus::DRAFT;
            }
            if (empty($model->calibration_status)) {
                $model->calibration_status = CalibrationProfileStatus::CALIBRATION_STATUS_NOT_CONFIGURED;
            }
            if (empty($model->profile_version)) {
                $model->profile_version = 'v1';
            }
        });
    }

    public function components(): HasMany
    {
        return $this->hasMany(CalibrationComponent::class, 'profile_id', 'id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', CalibrationProfileStatus::ACTIVE);
    }

    public function isEditable(): bool
    {
        return CalibrationProfileStatus::isEditable($this->status);
    }

    public function requiresReasonForEdit(): bool
    {
        return CalibrationProfileStatus::requiresReasonForEdit($this->status);
    }
}
