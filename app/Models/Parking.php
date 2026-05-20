<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Parking extends Model
{
    use HasFactory;

    protected $table = 'parkings';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'code', 'name', 'site_id', 'area_id', 'capacity', 'occupied_count', 'status_code', 'current_vehicle_id', 'is_active', 'created_by_user_id', 'updated_by_user_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'occupied_count' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function site()
    {
        return $this->belongsTo(\App\Models\Site::class, 'site_id', 'id');
    }

    public function area()
    {
        return $this->belongsTo(\App\Models\PlantArea::class, 'area_id', 'id');
    }

    public function currentVehicle()
    {
        return $this->belongsTo(\App\Models\Vehicle::class, 'current_vehicle_id', 'id');
    }
}
