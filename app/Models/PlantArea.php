<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlantArea extends Model
{
    use HasFactory;

    protected $table = 'plant_areas';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'plant_configuration_id',
        'site_id',
        'code',
        'name',
        'area_type',
        'description',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'draft';
            }
        });
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }

    public function plantConfiguration()
    {
        return $this->belongsTo(PlantConfiguration::class, 'plant_configuration_id', 'id');
    }
}
