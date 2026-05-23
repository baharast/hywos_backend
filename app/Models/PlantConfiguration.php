<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlantConfiguration extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'site_id',
        'company_id',
        'status',
        'version',
        'company_name',
        'company_code',
        'site_name',
        'site_code',
        'plant_type',
        'default_language',
        'time_zone',
        'validation_summary',
        'activated_at',
        'activated_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'validation_summary' => 'array',
        'activated_at' => 'datetime',
        'version' => 'integer',
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
            if (empty($model->version)) {
                $model->version = 1;
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->status === 'active_locked';
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function plantAreas()
    {
        return $this->hasMany(PlantArea::class, 'plant_configuration_id', 'id');
    }

    public function gates()
    {
        return $this->hasMany(Gate::class, 'plant_configuration_id', 'id');
    }

    public function terminalsPanels()
    {
        return $this->hasMany(TerminalPanel::class, 'plant_configuration_id', 'id');
    }

    public function bayLines()
    {
        return $this->hasMany(BayLine::class, 'plant_configuration_id', 'id');
    }

    public function parkings()
    {
        return $this->hasMany(Parking::class, 'plant_configuration_id', 'id');
    }

    public function changeRequests()
    {
        return $this->hasMany(PlantConfigurationChangeRequest::class, 'plant_configuration_id', 'id');
    }
}
