<?php

namespace App\Services\QualitySpecs;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\GasComponent;
use App\Enums\ProductSpecStatus;
use App\Models\ProductGasLimit;
use App\Models\ProductSpecification;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for Product Specification + Gas Limit lifecycle.
 *
 * Responsibilities:
 *   - create / update / activate / retire the spec header
 *   - add / update one gas-limit row at a time
 *   - enforce the V2.1 §9 invariants (at least one of lower/upper,
 *     all 6 components configured before activate, no duplicate
 *     component per spec)
 *   - emit one audit + one event row per mutation
 *
 * The controller is a thin HTTP layer; every state change passes through
 * here so the audit trail is consistent.
 */
class ProductSpecificationService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {}

    public function createDraft(array $data): ProductSpecification
    {
        return DB::transaction(function () use ($data) {
            $spec = ProductSpecification::create([
                'product_code' => $data['product_code'],
                'quality_class' => $data['quality_class'],
                'display_name' => $data['display_name'],
                'spec_version' => $data['spec_version'] ?? 'v1',
                'status' => ProductSpecStatus::DRAFT,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'notes' => $data['notes'] ?? null,
                'correlation_id' => request()?->header('X-Correlation-Id'),
            ]);

            $this->audit->record(
                $spec,
                AuditAction::PRODUCT_SPEC_CREATED,
                null,
                $this->audit->snapshotModel($spec),
                null,
                null
            );
            $this->events->record(
                'product_spec.created',
                $spec,
                "Product spec {$spec->product_code} {$spec->spec_version} created (draft)",
                ['product_code' => $spec->product_code, 'spec_version' => $spec->spec_version],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $spec->fresh();
        });
    }

    public function updateMetadata(ProductSpecification $spec, array $data): ProductSpecification
    {
        return DB::transaction(function () use ($spec, $data) {
            $old = $this->audit->snapshotModel($spec);

            $spec->fill([
                'display_name' => $data['display_name'] ?? $spec->display_name,
                'quality_class' => $data['quality_class'] ?? $spec->quality_class,
                'effective_from' => array_key_exists('effective_from', $data) ? $data['effective_from'] : $spec->effective_from,
                'effective_to' => array_key_exists('effective_to', $data) ? $data['effective_to'] : $spec->effective_to,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $spec->notes,
            ])->save();

            $this->audit->record(
                $spec,
                AuditAction::PRODUCT_SPEC_UPDATED,
                $old,
                $this->audit->snapshotModel($spec->fresh()),
                $data['reason'] ?? null,
                null
            );
            $this->events->record(
                'product_spec.updated',
                $spec,
                "Product spec {$spec->product_code} {$spec->spec_version} updated",
                ['product_code' => $spec->product_code, 'spec_version' => $spec->spec_version],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $spec->fresh();
        });
    }

    /**
     * Flip draft → active. Rejects with 'incomplete' code if not all 6
     * components have a gas-limit row.
     *
     * @return array{ok: bool, code?: string, spec?: ProductSpecification}
     */
    public function activate(ProductSpecification $spec, ?string $reason = null): array
    {
        if ($spec->status !== ProductSpecStatus::DRAFT) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        $configured = $spec->gasLimits()->pluck('component')->all();
        $missing = array_values(array_diff(GasComponent::all(), $configured));
        if (count($missing) > 0) {
            return [
                'ok' => false,
                'code' => 'PRODUCT_SPEC_INCOMPLETE',
                'details' => ['missing' => $missing],
            ];
        }

        $fresh = DB::transaction(function () use ($spec, $reason) {
            $old = $this->audit->snapshotModel($spec);

            $spec->status = ProductSpecStatus::ACTIVE;
            $spec->activated_at = now();
            $spec->save();

            $this->audit->record(
                $spec,
                AuditAction::PRODUCT_SPEC_ACTIVATED,
                $old,
                $this->audit->snapshotModel($spec->fresh()),
                $reason,
                null
            );
            $this->events->record(
                'product_spec.activated',
                $spec,
                "Product spec {$spec->product_code} {$spec->spec_version} activated",
                ['product_code' => $spec->product_code, 'spec_version' => $spec->spec_version],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $spec->fresh();
        });

        return ['ok' => true, 'spec' => $fresh];
    }

    public function retire(ProductSpecification $spec, string $reason): array
    {
        if ($spec->status !== ProductSpecStatus::ACTIVE) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        $fresh = DB::transaction(function () use ($spec, $reason) {
            $old = $this->audit->snapshotModel($spec);

            $spec->status = ProductSpecStatus::RETIRED;
            $spec->retired_at = now();
            $spec->save();

            $this->audit->record(
                $spec,
                AuditAction::PRODUCT_SPEC_RETIRED,
                $old,
                $this->audit->snapshotModel($spec->fresh()),
                $reason,
                null
            );
            $this->events->record(
                'product_spec.retired',
                $spec,
                "Product spec {$spec->product_code} {$spec->spec_version} retired",
                ['product_code' => $spec->product_code, 'spec_version' => $spec->spec_version, 'reason' => $reason],
                EventCategory::QUALITY,
                EventSeverity::WARNING
            );

            return $spec->fresh();
        });

        return ['ok' => true, 'spec' => $fresh];
    }

    /**
     * Add a gas-limit row. Returns ['ok'=>false,'code'=>...] if the
     * component already has a row on this spec.
     *
     * @return array{ok: bool, code?: string, row?: ProductGasLimit}
     */
    public function addGasLimit(ProductSpecification $spec, array $data): array
    {
        $existing = $spec->gasLimits()->where('component', $data['component'])->first();
        if ($existing) {
            return ['ok' => false, 'code' => 'PRODUCT_SPEC_GAS_LIMIT_EXISTS'];
        }

        $row = DB::transaction(function () use ($spec, $data) {
            $row = ProductGasLimit::create([
                'spec_id' => $spec->id,
                'component' => $data['component'],
                'unit' => $data['unit'],
                'lower_limit' => $data['lower_limit'] ?? null,
                'upper_limit' => $data['upper_limit'] ?? null,
                'warning_limit' => $data['warning_limit'] ?? null,
                'critical_limit' => $data['critical_limit'] ?? null,
                'precision_decimals' => $data['precision_decimals'] ?? null,
                'rounding_rule' => $data['rounding_rule'] ?? null,
                'applies_to_analysis_types' => $data['applies_to_analysis_types'],
                'required_for_release' => $data['required_for_release'] ?? true,
                'certificate_mapping' => $data['certificate_mapping'],
                'display_order' => $data['display_order'] ?? GasComponent::displayOrder($data['component']),
                'last_change_reason' => $data['reason'] ?? null,
            ]);

            $this->audit->record(
                $row,
                AuditAction::PRODUCT_SPEC_GAS_LIMIT_ADDED,
                null,
                $this->audit->snapshotModel($row),
                $data['reason'] ?? null,
                null
            );
            $this->events->record(
                'product_spec.gas_limit_added',
                $row,
                "Gas limit row added: {$row->component} on {$spec->product_code} {$spec->spec_version}",
                [
                    'spec_id' => $spec->id,
                    'product_code' => $spec->product_code,
                    'component' => $row->component,
                ],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $row;
        });

        return ['ok' => true, 'row' => $row];
    }

    public function updateGasLimit(ProductGasLimit $row, array $data): ProductGasLimit
    {
        return DB::transaction(function () use ($row, $data) {
            $old = $this->audit->snapshotModel($row);

            $fields = [
                'unit', 'lower_limit', 'upper_limit', 'warning_limit',
                'critical_limit', 'precision_decimals', 'rounding_rule',
                'applies_to_analysis_types', 'required_for_release',
                'certificate_mapping', 'display_order',
            ];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $row->{$f} = $data[$f];
                }
            }
            $row->last_change_reason = $data['reason'] ?? $row->last_change_reason;
            $row->save();

            $this->audit->record(
                $row,
                AuditAction::PRODUCT_SPEC_GAS_LIMIT_UPDATED,
                $old,
                $this->audit->snapshotModel($row->fresh()),
                $data['reason'] ?? null,
                null
            );
            $this->events->record(
                'product_spec.gas_limit_updated',
                $row,
                "Gas limit row updated: {$row->component}",
                ['spec_id' => $row->spec_id, 'component' => $row->component],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $row->fresh();
        });
    }
}
