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
    protected $fillable = ['id', 'site_id', 'code', 'name'];

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
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }
}
