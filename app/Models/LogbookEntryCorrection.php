<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Append-only correction record for V1 §7.5 logbook entries.
 *
 * The saving hook restores ANY field except the table's own id /
 * timestamps on subsequent saves — same immutability pattern as
 * document_print_attempts. A new correction always lands as a new row.
 */
class LogbookEntryCorrection extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $table = 'logbook_entry_corrections';

    protected $fillable = [
        'id', 'logbook_entry_id',
        'corrected_at', 'corrected_by_user_id', 'corrected_by_name',
        'old_title', 'old_description', 'old_action_taken',
        'new_title', 'new_description', 'new_action_taken',
        'reason',
        'correlation_id',
    ];

    protected $casts = [
        'corrected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LogbookEntryCorrection $m): void {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
            if (empty($m->corrected_at)) {
                $m->corrected_at = now();
            }
        });

        static::saving(function (LogbookEntryCorrection $m): void {
            if (! $m->exists) return;
            foreach (array_keys($m->getDirty()) as $field) {
                $original = $m->getOriginal($field);
                Log::warning("LogbookEntryCorrection immutable field [{$field}] mutated; restoring original.", [
                    'correction_id' => $m->id,
                    'logbook_entry_id' => $m->logbook_entry_id,
                ]);
                $m->setRawAttributes(array_merge($m->getAttributes(), [$field => $original]));
            }
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class, 'logbook_entry_id', 'id');
    }
}
