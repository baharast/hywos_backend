<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AnalysisAttempt extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'analysis_id',
        'attempt_no',
        'status',
        'latest_message',
        'triggered_by',
        'started_at',
        'finished_at',
        'is_repeat',
        'request_reason',
        'correlation_id',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'is_repeat' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalysisAttempt $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(ActiveAnalysis::class, 'analysis_id', 'id');
    }

    public function elementResults(): HasMany
    {
        return $this->hasMany(AnalysisElementResult::class, 'attempt_id', 'id');
    }
}
