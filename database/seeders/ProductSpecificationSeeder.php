<?php

namespace Database\Seeders;

use App\Enums\AnalysisTypeApplicable;
use App\Enums\CertificateMapping;
use App\Enums\GasComponent;
use App\Enums\ProductSpecStatus;
use App\Models\ProductGasLimit;
use App\Models\ProductSpecification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo seed for Products & Quality Specifications (V2.1):
 *   - H2-5.0 v1 ACTIVE with all 6 components configured per spec §4.3.
 *   - H2-3.5 v1 DRAFT with only 4/6 components — so the FE can demo the
 *     incomplete-state UI without manual setup.
 */
class ProductSpecificationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedH2_5_0_active();
        $this->seedH2_3_5_draftIncomplete();
    }

    protected function seedH2_5_0_active(): void
    {
        $spec = ProductSpecification::firstOrCreate(
            ['product_code' => 'H2-5.0', 'spec_version' => 'v1'],
            [
                'id' => (string) Str::uuid(),
                'quality_class' => '5.0',
                'display_name' => 'Hydrogen 5.0',
                'status' => ProductSpecStatus::ACTIVE,
                'activated_at' => now()->subDays(7),
                'effective_from' => now()->subDays(7),
                'notes' => 'Demo spec for H2 5.0 — purity floor 99.999%.',
            ]
        );

        $appliesTo = [
            AnalysisTypeApplicable::PRE_ANALYSIS,
            AnalysisTypeApplicable::MAIN_ANALYSIS,
            AnalysisTypeApplicable::FINAL_ANALYSIS,
        ];

        $rows = [
            // H2 purity: lower limit only
            [GasComponent::H2,  '%',  99.999, null, null, null],
            // Impurities: upper limit only
            [GasComponent::O2,  'ppm', null, 1.0, null, null],
            [GasComponent::N2,  'ppm', null, 5.0, null, null],
            [GasComponent::CH4, 'ppm', null, 1.0, null, null],
            [GasComponent::CO,  'ppm', null, 0.2, null, null],
            [GasComponent::CO2, 'ppm', null, 2.0, null, null],
        ];

        foreach ($rows as [$comp, $unit, $lower, $upper, $warn, $crit]) {
            ProductGasLimit::firstOrCreate(
                ['spec_id' => $spec->id, 'component' => $comp],
                [
                    'id' => (string) Str::uuid(),
                    'unit' => $unit,
                    'lower_limit' => $lower,
                    'upper_limit' => $upper,
                    'warning_limit' => $warn,
                    'critical_limit' => $crit,
                    'precision_decimals' => 4,
                    'rounding_rule' => 'round',
                    'applies_to_analysis_types' => $appliesTo,
                    'required_for_release' => true,
                    'certificate_mapping' => CertificateMapping::CERTIFICATE_ROW,
                    'display_order' => GasComponent::displayOrder($comp),
                ]
            );
        }
    }

    protected function seedH2_3_5_draftIncomplete(): void
    {
        $spec = ProductSpecification::firstOrCreate(
            ['product_code' => 'H2-3.5', 'spec_version' => 'v1'],
            [
                'id' => (string) Str::uuid(),
                'quality_class' => '3.5',
                'display_name' => 'Hydrogen 3.5 (demo draft)',
                'status' => ProductSpecStatus::DRAFT,
                'notes' => 'Demo draft — only 4/6 components configured so the FE can demonstrate the incomplete-spec UI state. Activate will reject until CH4 and CO2 rows are added.',
            ]
        );

        $appliesTo = [
            AnalysisTypeApplicable::PRE_ANALYSIS,
            AnalysisTypeApplicable::MAIN_ANALYSIS,
        ];

        // Only 4 components — CH4 and CO2 deliberately omitted.
        $rows = [
            [GasComponent::H2, '%',   99.95, null, null, null],
            [GasComponent::O2, 'ppm', null,  10.0, null, null],
            [GasComponent::N2, 'ppm', null,  50.0, null, null],
            [GasComponent::CO, 'ppm', null,  1.0,  null, null],
        ];

        foreach ($rows as [$comp, $unit, $lower, $upper, $warn, $crit]) {
            ProductGasLimit::firstOrCreate(
                ['spec_id' => $spec->id, 'component' => $comp],
                [
                    'id' => (string) Str::uuid(),
                    'unit' => $unit,
                    'lower_limit' => $lower,
                    'upper_limit' => $upper,
                    'warning_limit' => $warn,
                    'critical_limit' => $crit,
                    'precision_decimals' => 3,
                    'rounding_rule' => 'round',
                    'applies_to_analysis_types' => $appliesTo,
                    'required_for_release' => true,
                    'certificate_mapping' => CertificateMapping::CERTIFICATE_ROW,
                    'display_order' => GasComponent::displayOrder($comp),
                ]
            );
        }
    }
}
