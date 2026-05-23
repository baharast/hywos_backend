<?php

namespace App\Models;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\DriverStatus;
use App\Enums\IdentificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Driver extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'driver_code',
        'first_name',
        'last_name',
        'national_id_last4',
        'national_id_hash',
        'license_no',
        'license_expiry_date',
        'phone',
        'email',
        'preferred_culture_code',
        'training_status',
        'training_valid_until',
        'block_status',
        'block_reason',
        'blocked_at',
        'blocked_by_user_id',
        'is_active',
        'employer_company_id',
        'operator_company_id',
        'avatar_file_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $hidden = [
        'national_id_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'license_expiry_date' => 'date',
        'training_valid_until' => 'date',
        'blocked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->block_status)) {
                $model->block_status = 'clear';
            }
            if (empty($model->preferred_culture_code)) {
                $model->preferred_culture_code = 'de';
            }
            if (empty($model->training_status)) {
                $model->training_status = 'unknown';
            }
        });
    }

    public function employerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'employer_company_id', 'id');
    }

    public function operatorCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'operator_company_id', 'id');
    }

    public function authMedia(): HasMany
    {
        return $this->hasMany(AuthMedium::class, 'driver_id', 'id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        $first = $this->first_name ? mb_substr($this->first_name, 0, 1) : '';
        $last = $this->last_name ? mb_substr($this->last_name, 0, 1) : '';
        return mb_strtoupper($first . $last);
    }

    public function getStatusAttribute(): string
    {
        if ($this->block_status === 'blocked') {
            return DriverStatus::BLOCKED;
        }
        if ($this->is_active === false) {
            return DriverStatus::INACTIVE;
        }
        if ($this->is_active === true) {
            return DriverStatus::ACTIVE;
        }
        return DriverStatus::UNKNOWN;
    }

    public function getIdentificationStatusAttribute(): string
    {
        if ($this->block_status === 'blocked') {
            return IdentificationStatus::BLOCKED;
        }

        $activeChip = $this->authMedia
            ->where('medium_type', AuthMediumType::CHIP_CARD)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->count();
        if ($activeChip > 0) {
            return IdentificationStatus::CHIP_ASSIGNED;
        }

        $activeTan = $this->authMedia
            ->where('medium_type', AuthMediumType::TAN)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->count();
        if ($activeTan > 0) {
            return IdentificationStatus::TAN_AVAILABLE;
        }

        return IdentificationStatus::MISSING;
    }
}
