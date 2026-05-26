<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnalysisDeviceChannel extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_id',
        'channel_code',
        'label',
        'gas',
        'severity',
        'measured_value',
        'unit',
        'acknowledged',
        'inhibited',
        'last_message',
        'last_value_at',
    ];

    protected $casts = [
        'measured_value' => 'float',
        'acknowledged' => 'boolean',
        'inhibited' => 'boolean',
        'last_value_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalysisDeviceChannel $row): void {
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
