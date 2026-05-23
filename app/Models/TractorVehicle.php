<?php

namespace App\Models;

use App\Enums\CouplingState;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TractorVehicle extends Model
{
    use HasFactory;

    protected $table = 'tractor_vehicles';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'vehicle_code',
        'license_plate',
        'plate_country',
        'vehicle_type',
        'carrier_id',
        'owner_name',
        'default_driver_id',
        'vin',
        'registration_expiry',
        'insurance_expiry',
        'status',
        'block_reason',
        'blocked_at',
        'blocked_by_user_id',
        'is_active',
        'current_trailer_id',
        'current_trailer_label',
        'current_visit_id',
        'current_visit_no',
        'last_visit_at',
        'last_driver_name',
        'has_open_clarification',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_open_clarification' => 'boolean',
        'registration_expiry' => 'date',
        'insurance_expiry' => 'date',
        'blocked_at' => 'datetime',
        'last_visit_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = VehicleStatus::ACTIVE;
            }
        });
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'carrier_id', 'id');
    }

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'default_driver_id', 'id');
    }

    public function couplings(): HasMany
    {
        return $this->hasMany(TractorTrailerCoupling::class, 'tractor_vehicle_id', 'id')
            ->orderByDesc('coupled_at');
    }

    public function activeCoupling(): HasOne
    {
        return $this->hasOne(TractorTrailerCoupling::class, 'tractor_vehicle_id', 'id')
            ->where('is_active', true);
    }

    public function getCouplingStateAttribute(): string
    {
        if (! is_null($this->current_trailer_id) || ! is_null($this->current_trailer_label)) {
            return CouplingState::COUPLED;
        }
        if ($this->relationLoaded('activeCoupling') && $this->activeCoupling) {
            return CouplingState::COUPLED;
        }
        return CouplingState::NOT_COUPLED;
    }
}
