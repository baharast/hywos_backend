<?php

namespace App\Models;

use App\Enums\BlockingImpact;
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
        'source',
        'blocking_impact',
        'primary_action',
        'action_needed',
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
            if (empty($model->blocking_impact)) {
                $model->blocking_impact = BlockingImpact::NONE;
            }
            // Keep the legacy is_blocking flag in sync with blocking_impact
            // when the caller didn't pin it explicitly.
            if (is_null($model->is_blocking)) {
                $model->is_blocking = BlockingImpact::isBlocking($model->blocking_impact);
            }
            if (empty($model->opened_at)) {
                $model->opened_at = now();
            }
            if (empty($model->case_no)) {
                $model->case_no = static::nextCaseNo();
            }
        });

        static::updating(function (ClarificationCase $model): void {
            // If blocking_impact moves and the caller didn't also touch
            // is_blocking, keep them coherent.
            if ($model->isDirty('blocking_impact') && ! $model->isDirty('is_blocking')) {
                $model->is_blocking = BlockingImpact::isBlocking($model->blocking_impact);
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

    /**
     * Cases that are still operationally open — open, in_progress or
     * waiting_for_owner. Resolved/closed are excluded.
     */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ClarificationStatus::openStatuses());
    }

    public function scopeForEntity(Builder $q, string $type, string $id): Builder
    {
        return $q->where('entity_type', $type)->where('entity_id', $id);
    }

    /**
     * Open cases that currently block an operational process. Uses
     * `blocking_impact != 'none'` as the source of truth and falls back to
     * the legacy `is_blocking` boolean for rows created before V1.3 fields
     * were populated.
     */
    public function scopeBlocking(Builder $q): Builder
    {
        return $q->whereIn('status', ClarificationStatus::openStatuses())
            ->where(function (Builder $w): void {
                $w->where('is_blocking', true)
                    ->orWhere(function (Builder $x): void {
                        $x->whereNotNull('blocking_impact')
                            ->where('blocking_impact', '!=', BlockingImpact::NONE);
                    });
            });
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
