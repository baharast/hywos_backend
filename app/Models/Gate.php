<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Gate extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'plant_configuration_id',
        'site_id',
        'plant_area_id',
        'code',
        'name',
        'gate_type',
        'related_terminal_id',
        'related_device_id',
        'notes',
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

    public function plantArea()
    {
        return $this->belongsTo(PlantArea::class, 'plant_area_id', 'id');
    }

    public function plantConfiguration()
    {
        return $this->belongsTo(PlantConfiguration::class, 'plant_configuration_id', 'id');
    }

    public function relatedTerminal()
    {
        return $this->belongsTo(TerminalPanel::class, 'related_terminal_id', 'id');
    }
}
