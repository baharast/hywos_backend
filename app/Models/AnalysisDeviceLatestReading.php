<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnalysisDeviceLatestReading extends Model
{
    use HasFactory;

    protected $table = 'analysis_device_latest_readings';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_id',
        'component',
        'value',
        'unit',
        'validity',
        'measured_at',
    ];

    protected $casts = [
        'value' => 'float',
        'measured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalysisDeviceLatestReading $row): void {
            if (empty($row->{$row->getKeyName()})) {
                $row->{$row->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AnalysisDevice::class, 'device_id', 'id');
    }
}
