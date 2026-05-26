<?php

namespace App\Models;

use App\Enums\AnalysisDeviceHealthStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AnalysisDevice extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',
        'device_type',
        'health_status',
        'run_state',
        'calibration_status',
        'active_method',
        'selected_sample_point',
        'safety_state',
        'routing_state',
        'selected_stream',
        'mode',
        'inhibit_active',
        'last_message',
        'last_heartbeat_at',
        'last_value_at',
        'next_calibration_due_at',
        'site_id',
        'plant_area_id',
        'correlation_id',
    ];

    protected $casts = [
        'inhibit_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'last_value_at' => 'datetime',
        'next_calibration_due_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalysisDevice $row): void {
            if (empty($row->{$row->getKeyName()})) {
                $row->{$row->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function channels(): HasMany
    {
        return $this->hasMany(AnalysisDeviceChannel::class, 'device_id', 'id');
    }

    public function latestReadings(): HasMany
    {
        return $this->hasMany(AnalysisDeviceLatestReading::class, 'device_id', 'id');
    }

    /**
     * Anything that isn't healthy. Useful for the page header strip and
     * the priority-sort default on the cards row.
     */
    public function scopeAbnormal(Builder $q): Builder
    {
        return $q->where('health_status', '!=', AnalysisDeviceHealthStatus::HEALTHY);
    }
}
