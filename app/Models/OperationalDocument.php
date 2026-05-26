<?php

namespace App\Models;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\DocumentPrintStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OperationalDocument extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'operational_documents';

    protected $fillable = [
        'id',
        'document_no',
        'document_type',
        'lifecycle_status',
        'print_status',
        'is_exit_blocking',
        'blocking_reason',
        'blocker_type',
        'version',
        'template_name',
        'template_version',
        'file_url',
        'order_id',
        'order_no',
        'sap_order_no',
        'plant_visit_id',
        'visit_no',
        'driver_id',
        'driver_name',
        'trailer_id',
        'trailer_label',
        'tractor_id',
        'tractor_label',
        'customer_id',
        'customer_name',
        'carrier_id',
        'carrier_name',
        'analysis_id',
        'analysis_status',
        'product_quality',
        'actual_loaded_quantity',
        'unit',
        'printer_id',
        'printer_name',
        'print_job_id',
        'reprint_count',
        'last_failure_reason',
        'generated_at',
        'queued_at',
        'printed_at',
        'blocked_at',
        'handed_over_at',
        'handed_over_by_user_id',
        'handover_note',
        'invalidated_at',
        'invalidated_by_user_id',
        'invalidation_reason',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'generated_by_user_id',
        'generated_by_source',
        'snapshot_payload',
        'correlation_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_exit_blocking' => 'boolean',
        'reprint_count' => 'integer',
        'actual_loaded_quantity' => 'decimal:3',
        'snapshot_payload' => 'array',
        'generated_at' => 'datetime',
        'queued_at' => 'datetime',
        'printed_at' => 'datetime',
        'blocked_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Once a snapshot_payload has been populated (at generation time), it
     * is frozen — later updates to the column are silently restored to the
     * original value and a warning is logged. This implements V1.2 §11.1
     * "Snapshot Fields — Document snapshot values used at generation time"
     * and §18 "Immutable generated versions".
     */
    protected static function booted(): void
    {
        static::creating(function (OperationalDocument $m): void {
            if (empty($m->{$m->getKeyName()})) {
                $m->{$m->getKeyName()} = (string) Str::uuid();
            }
            if (empty($m->lifecycle_status)) {
                $m->lifecycle_status = DocumentLifecycleStatus::PENDING;
            }
            if (empty($m->print_status)) {
                $m->print_status = DocumentPrintStatus::NOT_REQUESTED;
            }
            if (is_null($m->reprint_count)) {
                $m->reprint_count = 0;
            }
            if (empty($m->document_no)) {
                $m->document_no = static::nextDocumentNo($m->document_type);
            }
        });

        static::saving(function (OperationalDocument $m): void {
            if ($m->exists && $m->isDirty('snapshot_payload')) {
                $original = $m->getOriginal('snapshot_payload');
                if (! empty($original)) {
                    Log::warning('OperationalDocument attempt to mutate immutable snapshot_payload; restoring original.', [
                        'document_id' => $m->id,
                        'document_no' => $m->document_no,
                    ]);
                    $m->setRawAttributes(
                        array_merge($m->getAttributes(), ['snapshot_payload' => $original])
                    );
                }
            }
        });
    }

    /**
     * Generate `DOC-YYYY-NNNN` style number. A per-type prefix would be
     * nicer (CERT-/DN-/QM-) but the spec calls out a single Document Number
     * label in §15, so we keep one shared series for MVP.
     */
    public static function nextDocumentNo(?string $type = null): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', now()->year)->count();
        $seq = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
        $prefix = match ($type) {
            'certificate' => 'CERT',
            'delivery_note' => 'DN',
            'qm_document' => 'QM',
            default => 'DOC',
        };
        return "{$prefix}-{$year}-{$seq}";
    }

    /* ----- Scopes ----- */

    public function scopeBlocking(Builder $q): Builder
    {
        return $q->where('is_exit_blocking', true);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('lifecycle_status', DocumentLifecycleStatus::activeStatuses());
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('document_type', $type);
    }

    public function scopeOfStatus(Builder $q, string $status): Builder
    {
        return $q->where('lifecycle_status', $status);
    }

    public function scopeOfPrintStatus(Builder $q, string $status): Builder
    {
        return $q->where('print_status', $status);
    }

    /* ----- Relations ----- */

    public function printAttempts()
    {
        return $this->hasMany(DocumentPrintAttempt::class, 'document_id', 'id')
            ->orderBy('attempt_no');
    }

    public function latestPrintAttempt()
    {
        return $this->hasOne(DocumentPrintAttempt::class, 'document_id', 'id')
            ->latestOfMany('attempt_no');
    }
}
