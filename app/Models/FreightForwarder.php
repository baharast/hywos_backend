<?php

namespace App\Models;

use App\Enums\CarrierApprovalState;
use App\Enums\CarrierStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FreightForwarder extends Model
{
    use HasFactory;

    protected $table = 'freight_forwarders';

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Fields owned by SAP when `is_sap_owned = true`.
     * Local edits to these fields are rejected by SapFieldGuard; changes must
     * flow through a controlled correction process instead.
     */
    public const SAP_OWNED_FIELDS = [
        'carrier_code',
        'carrier_name',
        'legal_name',
        'sap_reference',
    ];

    protected $fillable = [
        'id',
        'carrier_code',
        'carrier_name',
        'legal_name',
        'sap_reference',
        'external_reference',

        'street',
        'postal_code',
        'city',
        'country',

        'primary_contact_name',
        'contact_email',
        'contact_phone',

        'approval_state',
        'approved_for_loading',

        'status',
        'block_reason',
        'blocked_at',
        'blocked_by_user_id',

        'notes',

        'is_sap_owned',
        'is_active',

        'company_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_sap_owned' => 'boolean',
        'approved_for_loading' => 'boolean',
        'blocked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = CarrierStatus::ACTIVE;
            }
            if (empty($model->approval_state)) {
                $model->approval_state = CarrierApprovalState::PENDING_REVIEW;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function getSapLinkStateAttribute(): string
    {
        return ! empty($this->sap_reference) ? 'linked' : 'missing';
    }

    public function getHasNotesAttribute(): bool
    {
        return ! empty($this->notes);
    }
}
