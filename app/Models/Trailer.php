<?php

namespace App\Models;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\InspectionState;
use App\Enums\TrailerChipState;
use App\Enums\TrailerStatus;
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
}
