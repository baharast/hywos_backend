<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single print/reprint attempt. Per FillTrack Operational Documents V1.2
 * §18 "Reprint must not overwrite original generation/print history",
 * existing rows are append-only — any update except a status/completed_at
 * progression on the most recent attempt is logged and reverted.
 */
class DocumentPrintAttempt extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $table = 'document_print_attempts';

    protected $fillable = [
        'id',
        'document_id',
        'attempt_no',
        'status',
        'printer_id',
        'printer_name',
        // V1.4 §6 — Printers-tab additive columns (soft FKs to
        // hardware_devices.id and sibling attempt rows).
        'printer_hardware_id',
        'print_job_id',
        'requested_at',
        'requested_by_user_id',
        'requested_by_label',
        'completed_at',
        'failure_reason',
        'is_reprint',
        'reprint_reason',
        'retry_of_attempt_id',
        'replacement_of_attempt_id',
        'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'is_reprint' => 'boolean',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Fields that may legitimately change after a row is created — the
     * controller progresses an in-flight attempt from queued → printing →
     * printed/failed. Everything else is frozen.
     */
    private const MUTABLE_AFTER_CREATE = [
        'status',
        'completed_at',
        'failure_reason',
        'print_job_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (DocumentPrintAttempt $m): void {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
            if (empty($m->requested_at)) {
                $m->requested_at = now();
            }
            if (empty($m->created_at)) {
                $m->created_at = now();
            }
        });

        static::saving(function (DocumentPrintAttempt $m): void {
            if (! $m->exists) {
                return;
            }
            foreach (array_keys($m->getDirty()) as $field) {
                if (in_array($field, self::MUTABLE_AFTER_CREATE, true)) {
                    continue;
                }
                $original = $m->getOriginal($field);
                Log::warning("DocumentPrintAttempt immutable field [{$field}] mutated; restoring original.", [
                    'attempt_id' => $m->id,
                    'document_id' => $m->document_id,
                ]);
                $m->setRawAttributes(array_merge($m->getAttributes(), [$field => $original]));
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(OperationalDocument::class, 'document_id', 'id');
    }
}
