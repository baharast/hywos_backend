<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'event_category',
        'severity',
        'entity_type',
        'entity_id',
        'actor_user_id',
        'actor_name',
        'message',
        'details',
        'correlation_id',
        'ip_address',
        'occurred_at',
    ];

    protected $casts = [
        'details' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
