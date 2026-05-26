<?php

namespace App\Models;

use App\Enums\GasComponent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One row per (spec, gas component). Carries the acceptance limits used
 * by Active Analyses to decide pass/fail on a measured sample.
 *
 * V2.1 §9: at least one of `lower_limit` / `upper_limit` is required.
 * H2 typically uses lower_limit (purity floor); impurities use upper.
 * Service-layer validation enforces this — the column itself stays
 * nullable so the FE add-row UX can present an empty form.
 */
class ProductGasLimit extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'spec_id',
        'component',
        'unit',
        'lower_limit',
        'upper_limit',
        'warning_limit',
        'critical_limit',
        'precision_decimals',
        'rounding_rule',
        'applies_to_analysis_types',
        'required_for_release',
        'certificate_mapping',
        'display_order',
        'created_by_user_id',
        'updated_by_user_id',
        'last_change_reason',
    ];

    protected $casts = [
        'lower_limit' => 'decimal:6',
        'upper_limit' => 'decimal:6',
        'warning_limit' => 'decimal:6',
        'critical_limit' => 'decimal:6',
        'precision_decimals' => 'integer',
        'applies_to_analysis_types' => 'array',
        'required_for_release' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductGasLimit $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            // Default display_order from the canonical gas order
            // (H2=1, O2=2, ...) so the FE table renders in the same
            // order it appears on the FillTrack certificate.
            if (is_null($model->display_order) || $model->display_order === 0) {
                $model->display_order = GasComponent::displayOrder($model->component);
            }
        });
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(ProductSpecification::class, 'spec_id', 'id');
    }
}
