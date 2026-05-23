<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Fields that are considered owned by SAP when `is_sap_owned = true`.
     * These cannot be changed via local edits; updates must go through a
     * controlled correction flow.
     */
    public const SAP_OWNED_FIELDS = [
        'code',
        'name',
        'legal_name',
        'sap_customer_no',
    ];

    protected $fillable = [
        'id',
        'code',
        'name',
        'legal_name',
        'sap_customer_no',
        'external_reference',
        'street',
        'postal_code',
        'city',
        'country',
        'primary_contact_name',
        'email',
        'phone',
        'document_requirements',
        'default_document_language',
        'status',
        'block_reason',
        'blocked_at',
        'blocked_by_user_id',
        'notes',
        'is_sap_owned',
        'is_active',
        'site_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_sap_owned' => 'boolean',
        'blocked_at' => 'datetime',
        'document_requirements' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'active';
            }
        });
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }

    public function getSapLinkStateAttribute(): string
    {
        return ! empty($this->sap_customer_no) ? 'linked' : 'missing';
    }

    public function getDocumentRequirementStateAttribute(): string
    {
        if (is_array($this->document_requirements) && count($this->document_requirements) > 0) {
            return 'complete';
        }
        return 'missing';
    }
}
