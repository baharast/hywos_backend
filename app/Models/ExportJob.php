<?php

namespace App\Models;

use App\Enums\ExportJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExportJob extends Model
{
    protected $table = 'export_jobs';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'display_name',
        'categories',
        'record_scope',
        'date_from',
        'date_to',
        'status_scope',
        'field_set',
        'format',
        'status',
        'requested_by_user_id',
        'requested_by_name',
        'file_path',
        'file_disk',
        'file_size_bytes',
        'record_count_estimate',
        'record_count_actual',
        'error_message',
        'warnings',
        'started_at',
        'ready_at',
        'failed_at',
        'expires_at',
        'correlation_id',
    ];

    protected $casts = [
        'categories' => 'array',
        'warnings' => 'array',
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'failed_at' => 'datetime',
        'expires_at' => 'datetime',
        'file_size_bytes' => 'integer',
        'record_count_estimate' => 'integer',
        'record_count_actual' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = ExportJobStatus::QUEUED;
            }
        });
    }

    public function isReady(): bool
    {
        return $this->status === ExportJobStatus::READY;
    }

    public function isFailed(): bool
    {
        return $this->status === ExportJobStatus::FAILED;
    }

    public function isExpired(): bool
    {
        return $this->status === ExportJobStatus::EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }
}
