<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlantConfigurationChangeRequest extends Model
{
    use HasFactory;

    protected $table = 'plant_configuration_change_requests';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'plant_configuration_id',
        'affected_object_type',
        'affected_object_id',
        'affected_object_label',
        'change_type',
        'current_values',
        'proposed_values',
        'reason',
        'reason_code',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'approved_at',
        'approved_by_user_id',
        'rejected_at',
        'rejected_by_user_id',
        'rejection_note',
        'applied_at',
        'applied_by_user_id',
        'correlation_id',
    ];

    protected $casts = [
        'current_values' => 'array',
        'proposed_values' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'submitted';
            }
        });
    }

    public function plantConfiguration()
    {
        return $this->belongsTo(PlantConfiguration::class, 'plant_configuration_id', 'id');
    }
}
