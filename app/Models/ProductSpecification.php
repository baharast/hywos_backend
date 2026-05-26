<?php

namespace App\Models;

use App\Enums\ProductSpecStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Product Specification header row. Holds the (product_code,
 * spec_version) identity + lifecycle status. The actual per-component
 * acceptance limits live in product_gas_limits and are reached via
 * `gasLimits()` / `gasLimitFor($component)`.
 */
class ProductSpecification extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'product_code',
        'quality_class',
        'display_name',
        'spec_version',
        'status',
        'effective_from',
        'effective_to',
        'notes',
        'activated_at',
        'activated_by_user_id',
        'retired_at',
        'retired_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'correlation_id',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductSpecification $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = ProductSpecStatus::DRAFT;
            }
            if (empty($model->spec_version)) {
                $model->spec_version = 'v1';
            }
        });
    }

    /* ----- Relations ----- */

    public function gasLimits(): HasMany
    {
        return $this->hasMany(ProductGasLimit::class, 'spec_id', 'id');
    }

    /* ----- Scopes ----- */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', ProductSpecStatus::ACTIVE);
    }

    public function scopeForProductCode(Builder $q, string $code): Builder
    {
        return $q->where('product_code', $code);
    }

    /* ----- Helpers ----- */

    public function isEditable(): bool
    {
        return ProductSpecStatus::isEditable($this->status);
    }

    public function requiresReasonForEdit(): bool
    {
        return ProductSpecStatus::requiresReasonForEdit($this->status);
    }
}
