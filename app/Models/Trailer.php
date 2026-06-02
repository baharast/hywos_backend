<?php

namespace App\Models;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\InspectionState;
use App\Enums\TrailerChipState;
use App\Enums\TrailerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Trailer extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'trailer_code',
        'trailer_label',
        'plate',
        'trailer_type',
        'pressure_class',
        'volume',
        'volume_unit',
        'approved_product_quality',
        'inspection_expiry_date',
        'inspection_reference',
        'technical_suitability',
        'status',
        'block_reason',
        'blocked_at',
        'blocked_by_user_id',
        'carrier_id',
        'customer_id',
        'current_parking_id',
        'current_context',
        'last_visit_at',
        'notes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'approved_product_quality' => 'array',
        'is_active' => 'boolean',
        'inspection_expiry_date' => 'date',
        'blocked_at' => 'datetime',
        'last_visit_at' => 'datetime',
        'volume' => 'decimal:3',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = TrailerStatus::ACTIVE;
            }
            if (empty($model->technical_suitability)) {
                $model->technical_suitability = 'incomplete';
            }
        });
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'carrier_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id', 'id');
    }

    public function currentParking(): BelongsTo
    {
        return $this->belongsTo(Parking::class, 'current_parking_id', 'id');
    }

    public function authMedia(): HasMany
    {
        return $this->hasMany(AuthMedium::class, 'trailer_id', 'id');
    }

    public function getChipStateAttribute(): string
    {
        if ($this->status === TrailerStatus::BLOCKED) {
            return TrailerChipState::BLOCKED;
        }

        $media = $this->relationLoaded('authMedia')
            ? $this->authMedia
            : $this->authMedia()->get();

        $activeChip = $media->first(function ($m) {
            return $m->medium_type === AuthMediumType::CHIP_CARD
                && $m->status === AuthMediumStatus::ACTIVE;
        });

        if ($activeChip) {
            return TrailerChipState::ASSIGNED;
        }

        return TrailerChipState::MISSING;
    }

    public function getChipDisplayValueAttribute(): ?string
    {
        $media = $this->relationLoaded('authMedia')
            ? $this->authMedia
            : $this->authMedia()->get();

        $activeChip = $media->first(function ($m) {
            return $m->medium_type === AuthMediumType::CHIP_CARD
                && $m->status === AuthMediumStatus::ACTIVE;
        });

        return $activeChip?->display_identifier;
    }

    public function getInspectionStateAttribute(): string
    {
        if (! $this->inspection_expiry_date) {
            return InspectionState::MISSING;
        }
        $now = now()->startOfDay();
        $expiry = $this->inspection_expiry_date->startOfDay();

        if ($expiry->lt($now)) {
            return InspectionState::EXPIRED;
        }
        if ($expiry->diffInDays($now) <= 30) {
            return InspectionState::EXPIRING_SOON;
        }
        return InspectionState::VALID;
    }

    public function getHasNotesAttribute(): bool
    {
        return ! empty($this->notes);
    }

    /* ============================================================
     * Trailer-assignment eligibility (order picker, 2026-06-02)
     *
     * A trailer may be assigned to a loading order when it is ACTIVE,
     * not blocked / archived, and carries at least ONE active credential
     * — either a chip OR a TAN (operator's choice, "chip یا TAN"). The
     * picker lists EVERY trailer and disables the ineligible ones,
     * tagging each with which credential it carries.
     *
     * These three predicates are the single source of truth:
     *   - scopeAssignable()              SQL filter (?assignable=true)
     *   - getAssignmentBlockReasonAttribute()  PHP guard (assign / create)
     *   - has_active_chip / has_active_tan      credential badge
     * They MUST stay in lock-step; the SQL scope mirrors the PHP reason
     * ladder exactly so the list and the assign endpoint never disagree.
     * ============================================================ */

    /**
     * Raw presence of an ACTIVE chip credential, independent of the
     * trailer's block status. Kept aligned with `chip_state` (chip_card
     * medium); broadening to `trailer_chip` is a deliberate future change,
     * not made here so the existing chip signal stays consistent.
     */
    public function getHasActiveChipAttribute(): bool
    {
        return $this->hasActiveMedium([AuthMediumType::CHIP_CARD]);
    }

    /**
     * Raw presence of an ACTIVE TAN bound to this trailer (auth_media
     * row with medium_type=tan, trailer_id=this, status=active).
     */
    public function getHasActiveTanAttribute(): bool
    {
        return $this->hasActiveMedium([AuthMediumType::TAN]);
    }

    /**
     * Machine reason the trailer cannot be assigned, in priority order, or
     * null when it IS assignable. The codes double as the assign/create
     * endpoint error codes (see LoadingOrderController::trailerAssignmentConflict).
     */
    public function getAssignmentBlockReasonAttribute(): ?string
    {
        if ($this->status === TrailerStatus::BLOCKED) {
            return 'TRAILER_BLOCKED';
        }
        if ($this->status !== TrailerStatus::ACTIVE || ! $this->is_active) {
            return 'TRAILER_INACTIVE';
        }
        if (! $this->has_active_chip && ! $this->has_active_tan) {
            return 'TRAILER_NO_CREDENTIAL';
        }
        return null;
    }

    public function getIsAssignableAttribute(): bool
    {
        return $this->assignment_block_reason === null;
    }

    /**
     * SQL mirror of the assignment_block_reason ladder: ACTIVE status,
     * is_active flag set, and at least one active chip OR tan medium.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query
            ->where('status', TrailerStatus::ACTIVE)
            ->where('is_active', true)
            ->whereHas('authMedia', function (Builder $q) {
                $q->where('status', AuthMediumStatus::ACTIVE)
                    ->whereIn('medium_type', [AuthMediumType::CHIP_CARD, AuthMediumType::TAN]);
            });
    }

    /**
     * True when the trailer has at least one ACTIVE auth medium of any of
     * the given types. Reuses the eager-loaded `authMedia` relation when
     * present (no N+1 on the list endpoint), else queries.
     *
     * @param  array<int,string>  $types
     */
    protected function hasActiveMedium(array $types): bool
    {
        $media = $this->relationLoaded('authMedia')
            ? $this->authMedia
            : $this->authMedia()->get();

        return $media->contains(function ($m) use ($types) {
            return in_array($m->medium_type, $types, true)
                && $m->status === AuthMediumStatus::ACTIVE;
        });
    }
}
