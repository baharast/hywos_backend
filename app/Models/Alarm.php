<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Alarm extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'alarms';

    protected $fillable = [
        'id', 'alarm_no', 'title',
        'severity', 'status', 'category', 'blocking_impact',
        'source_type', 'source_id', 'source_label', 'location',
        'owner_role', 'owner_user_id', 'owner_user_name',
        'linked_entity_type', 'linked_entity_id', 'linked_entity_label',
        'message', 'recommended_action', 'alarm_code',
        'current_value', 'threshold_value', 'unit',
        'technical_payload',
        'first_seen_at', 'last_seen_at', 'occurrence_count',
        'acknowledged_at', 'acknowledged_by_user_id', 'acknowledged_by_name',
        'in_progress_at', 'in_progress_by_user_id',
        'resolved_at', 'resolved_by_user_id', 'resolved_by_name',
        'resolution_reason', 'corrective_action', 'closed_at',
        'correlation_id',
    ];

    protected $casts = [
        'technical_payload' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'occurrence_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Alarm $m): void {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
            if (empty($m->alarm_no)) {
                $m->alarm_no = sprintf('ALM-%s-%s',
                    now()->format('Y'),
                    strtoupper(substr(hash('crc32b', $m->id), 0, 6)),
                );
            }
            if (empty($m->first_seen_at)) {
                $m->first_seen_at = now();
            }
        });
    }
}
