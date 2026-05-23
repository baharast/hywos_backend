<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TractorTrailerCoupling extends Model
{
    use HasFactory;

    protected $table = 'tractor_trailer_couplings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tractor_vehicle_id',
        'trailer_id',
        'trailer_label',
        'driver_id',
        'driver_name',
        'plant_visit_id',
        'visit_no',
        'order_id',
        'order_no',
        'source',
        'coupled_at',
        'uncoupled_at',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
        'correlation_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'coupled_at' => 'datetime',
        'uncoupled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (is_null($model->is_active)) {
                $model->is_active = true;
            }
        });

        // When uncoupled_at is set on save, mark the coupling inactive.
        static::saving(function ($model) {
            if (! is_null($model->uncoupled_at) && $model->is_active === true) {
                $model->is_active = false;
            }
        });
    }

    public function tractorVehicle(): BelongsTo
    {
        return $this->belongsTo(TractorVehicle::class, 'tractor_vehicle_id', 'id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }
}
