<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * V1 §7.5 Safety & Operations Logbook entry.
 *
 * Human-created shift / safety / operations note. The TITLE,
 * DESCRIPTION and ACTION_TAKEN fields can only be changed via the
 * correct() write surface — the saving hook restores them and routes
 * the change through LogbookEntryCorrection. Other fields (follow-up
 * progression, handover_flag toggle) are mutable through dedicated
 * write surfaces.
 */
class LogbookEntry extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'logbook_entries';

    protected $fillable = [
        'id', 'shift_label',
        'category', 'severity', 'area',
        'title', 'description', 'action_taken',
        'related_entity_type', 'related_entity_id',
        'linked_alarm_id', 'linked_event_log_id', 'linked_clarification_case_id',
        'follow_up_required', 'follow_up_owner_user_id', 'follow_up_owner_role',
        'follow_up_due_at', 'follow_up_status', 'follow_up_completed_at',
        'follow_up_completed_by_user_id', 'follow_up_completion_note',
        'handover_flag',
        'created_by_user_id', 'created_by_name',
        'correlation_id',
    ];

    protected $casts = [
        'follow_up_required' => 'boolean',
        'follow_up_due_at' => 'datetime',
        'follow_up_completed_at' => 'datetime',
        'handover_flag' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Fields locked after create — they may only change via correct().
     * The saving hook restores them on any other mutation attempt.
     */
    public const CONTENT_FIELDS = ['title', 'description', 'action_taken'];

    protected static function booted(): void
    {
        static::creating(function (LogbookEntry $m): void {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
            if (empty($m->follow_up_status)) {
                $m->follow_up_status = \App\Enums\LogbookFollowUpStatus::OPEN;
            }
        });
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(LogbookEntryCorrection::class, 'logbook_entry_id', 'id');
    }
}
