<?php

namespace App\Models;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\ChipCardAssignmentState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AuthMedium extends Model
{
    use HasFactory;

    protected $table = 'auth_media';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'medium_type',
        'identifier_value',
        'identifier_hash',
        'display_identifier',
        'driver_id',
        'trailer_id',
        'status',
        'is_single_use',
        'issued_at',
        'expires_at',
        'used_at',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
        'order_id',
        'created_by_user_id',

        // Chip-card-only columns (additive — TAN session owns its own set).
        'card_code',
        'serial_number',
        'masked_uid',
        'card_type',
        'assignment_state',
        'replacement_of_card_id',
        'replaced_by_card_id',
        'replacement_reason',
        'lost_at',
        'defective_at',
        'archived_at',
        'last_used_at',
        'last_used_context',
        'last_used_source',
        'last_usage_result',

        // TAN-only columns (additive — ChipCards module does not touch these).
        'tan_reference',
        'tan_masked',
        'valid_from',
        'consumed_at',
        'consumption_count',
        'usage_state',
        'tan_purpose',
        'related_plant_visit_id',
        'related_terminal_session_id',
        'reason',
    ];

    protected $hidden = [
        'identifier_value',
        'identifier_hash',
    ];

    protected $casts = [
        'is_single_use' => 'boolean',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',

        // Chip-card columns
        'lost_at' => 'datetime',
        'defective_at' => 'datetime',
        'archived_at' => 'datetime',
        'last_used_at' => 'datetime',

        // TAN columns
        'valid_from' => 'datetime',
        'consumed_at' => 'datetime',
        'consumption_count' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = AuthMediumStatus::ACTIVE;
            }
            if (empty($model->assignment_state)) {
                $model->assignment_state = ChipCardAssignmentState::UNASSIGNED;
            }
        });
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class, 'trailer_id', 'id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ChipCardAssignment::class, 'auth_medium_id', 'id')
            ->orderByDesc('created_at');
    }

    public function replacementOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_of_card_id', 'id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_card_id', 'id');
    }

    public function scopeChipCards(Builder $query): Builder
    {
        return $query->whereIn('medium_type', [
            AuthMediumType::CHIP_CARD,
            AuthMediumType::TRAILER_CHIP,
        ]);
    }

    public function scopeAssignedTo(Builder $query, string $entityType, string $entityId): Builder
    {
        $column = $entityType === 'driver' ? 'driver_id' : 'trailer_id';
        return $query
            ->where('assignment_state', ChipCardAssignmentState::ASSIGNED)
            ->where($column, $entityId);
    }

    public function scopeTans(Builder $query): Builder
    {
        return $query->where('medium_type', AuthMediumType::TAN);
    }

    public function scopeUnused(Builder $query): Builder
    {
        return $query
            ->where('usage_state', 'unused')
            ->where('status', AuthMediumStatus::ACTIVE);
    }

    public function scopeForDriver(Builder $query, string $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }
}
