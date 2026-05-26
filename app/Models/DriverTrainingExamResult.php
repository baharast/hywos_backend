<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * V6 §7.6 — one row per exam submission. Append-only history; every attempt
 * (pass or fail) is preserved.
 */
class DriverTrainingExamResult extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    /** @var bool no updated_at column */
    public $timestamps = false;

    protected $table = 'driver_training_exam_results';

    protected $fillable = [
        'id',
        'driver_id',
        'score',
        'total',
        'passed',
        'answers',
        'terminal_session_id',
        'correlation_id',
        'submitted_at',
        'created_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'total' => 'integer',
        'passed' => 'boolean',
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DriverTrainingExamResult $row): void {
            if (empty($row->{$row->getKeyName()})) {
                $row->{$row->getKeyName()} = (string) Str::uuid();
            }
            $now = now();
            if (empty($row->submitted_at)) {
                $row->submitted_at = $now;
            }
            if (empty($row->created_at)) {
                $row->created_at = $now;
            }
        });
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }
}
