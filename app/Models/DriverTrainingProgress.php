<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * V6 §7 — one row per driver+module. Append-only: there is no `updated_at`
 * column and the service performs an idempotent upsert into the (driver_id,
 * module_id) unique key. Always treat this model read-only after creation.
 */
class DriverTrainingProgress extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    /** @var bool no updated_at column */
    public $timestamps = false;

    protected $table = 'driver_training_progress';

    protected $fillable = [
        'id',
        'driver_id',
        'module_id',
        'completed_at',
        'terminal_session_id',
        'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DriverTrainingProgress $row): void {
            if (empty($row->{$row->getKeyName()})) {
                $row->{$row->getKeyName()} = (string) Str::uuid();
            }
            if (empty($row->completed_at)) {
                $row->completed_at = now();
            }
            if (empty($row->created_at)) {
                $row->created_at = now();
            }
        });
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }
}
