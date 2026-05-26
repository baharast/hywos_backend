<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnalysisElementResult extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'attempt_id',
        'analysis_id',
        'element',
        'measured_value',
        'unit',
        'lower_limit',
        'upper_limit',
        'limit_label',
        'difference_label',
        'status',
        'validity_reason',
        'measured_at',
    ];

    protected $casts = [
        'measured_value' => 'decimal:6',
        'lower_limit' => 'decimal:6',
        'upper_limit' => 'decimal:6',
        'measured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalysisElementResult $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AnalysisAttempt::class, 'attempt_id', 'id');
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(ActiveAnalysis::class, 'analysis_id', 'id');
    }
}
