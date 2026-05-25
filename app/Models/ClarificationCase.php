<?php

namespace App\Models;

use App\Enums\ClarificationEntityType;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClarificationCase extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'case_no',
        'status',
        'severity',
        'category',
        'title',
        'description',
        'reason_code',
        'entity_type',
        'entity_id',
        'entity_label',
        'related_plant_visit_id',
        'related_order_id',
        'related_driver_id',
        'related_trailer_id',
        'owner_role',
        'assigned_to_user_id',
        'opened_at',
        'opened_by_user_id',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_note',
        'closed_at',
        'closed_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'is_blocking',
        'correlation_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_blocking' => 'boolean',
        'opened_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClarificationCase $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = ClarificationStatus::OPEN;
            }
            if (empty($model->severity)) {
                $model->severity = ClarificationSeverity::NORMAL;
            }
            if (is_null($model->is_blocking)) {
                $model->is_blocking = true;
            }
            if (empty($model->opened_at)) {
                $model->opened_at = now();
            }
            if (empty($model->case_no)) {
                $model->case_no = static::nextCaseNo();
            }
        });
    }

    /**
     * Generate the next case_no in the form `CC-YYYY-NNNN`. Simple count+1
     * approach is fine for MVP — under high concurrency a unique-index retry
     * is the fallback.
     */
    public static function nextCaseNo(): string
    {
        $year = now()->format('Y');
        $countThisYear = static::query()->whereYear('created_at', now()->year)->count();
        $seq = str_pad((string) ($countThisYear + 1), 4, '0', STR_PAD_LEFT);
        return "CC-{$year}-{$seq}";
    }

    /* ----- Scopes ----- */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [ClarificationStatus::OPEN, ClarificationStatus::IN_REVIEW]);
    }

    public function scopeForEntity(Builder $q, string $type, string $id): Builder
    {
        return $q->where('entity_type', $type)->where('entity_id', $id);
    }

    public function scopeBlocking(Builder $q): Builder
    {
        return $q->where('is_blocking', true)
            ->whereIn('status', [ClarificationStatus::OPEN, ClarificationStatus::IN_REVIEW]);
    }

    /* ----- Accessors ----- */

    /**
     * FE deeplink path for the bound entity. Returns null when the entity
     * has no canonical detail screen yet.
     */
    public function getEntityLinkAttribute(): ?string
    {
        return ClarificationEntityType::routePathFor($this->entity_type, $this->entity_id);
    }
}
