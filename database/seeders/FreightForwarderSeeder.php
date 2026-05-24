<?php

namespace Database\Seeders;

use App\Enums\CarrierApprovalState;
use App\Enums\CarrierStatus;
use App\Models\FreightForwarder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FreightForwarderSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            [
                'carrier_code' => 'CARR-1001',
                'carrier_name' => 'Carrier GmbH',
                'legal_name' => 'Carrier GmbH & Co. KG',
                'sap_reference' => 'SAP-VEND-1001',
                'street' => 'Hauptstr. 12',
                'postal_code' => '97421',
                'city' => 'Schweinfurt',
                'country' => 'Germany',
                'primary_contact_name' => 'Anna Schmidt',
                'contact_email' => 'logistics@carrier.example',
                'contact_phone' => '+49 9721 0000001',
                'approval_state' => CarrierApprovalState::APPROVED,
                'approved_for_loading' => true,
                'status' => CarrierStatus::ACTIVE,
                'is_sap_owned' => true,
                'is_active' => true,
                'notes' => null,
            ],
            [
                'carrier_code' => 'CARR-1002',
                'carrier_name' => 'Logistics AG',
                'legal_name' => 'Logistics AG',
                'sap_reference' => 'SAP-VEND-1002',
                'street' => 'Industrieweg 5',
                'postal_code' => '80333',
                'city' => 'Munich',
                'country' => 'Germany',
                'primary_contact_name' => 'Klaus Müller',
                'contact_email' => 'dispatch@logistics-ag.example',
                'contact_phone' => '+49 89 0000002',
                'approval_state' => CarrierApprovalState::APPROVED,
                'approved_for_loading' => true,
                'status' => CarrierStatus::ACTIVE,
                'is_sap_owned' => true,
                'is_active' => true,
                'notes' => null,
            ],
            [
                'carrier_code' => 'CARR-1003',
                'carrier_name' => 'Local Transport Ltd.',
                'legal_name' => null,
                'sap_reference' => null,
                'street' => 'Bahnhofstr. 22',
                'postal_code' => '60311',
                'city' => 'Frankfurt',
                'country' => 'Germany',
                'primary_contact_name' => 'Sarah Becker',
                'contact_email' => 'office@localtransport.example',
                'contact_phone' => '+49 69 0000003',
                'approval_state' => CarrierApprovalState::PENDING_REVIEW,
                'approved_for_loading' => false,
                'status' => CarrierStatus::ACTIVE,
                'is_sap_owned' => false,
                'is_active' => true,
                'notes' => 'Awaiting insurance review.',
            ],
            [
                'carrier_code' => 'CARR-1004',
                'carrier_name' => 'North Freight Co.',
                'legal_name' => 'North Freight Co. GmbH',
                'sap_reference' => 'SAP-VEND-1004',
                'street' => 'Hafenstr. 3',
                'postal_code' => '20457',
                'city' => 'Hamburg',
                'country' => 'Germany',
                'primary_contact_name' => 'Lars Petersen',
                'contact_email' => 'ops@north-freight.example',
                'contact_phone' => '+49 40 0000004',
                'approval_state' => CarrierApprovalState::APPROVED,
                'approved_for_loading' => false,
                'status' => CarrierStatus::BLOCKED,
                'block_reason' => 'Insurance review',
                'blocked_at' => now(),
                'is_sap_owned' => true,
                'is_active' => true,
                'notes' => 'Blocked pending insurance review outcome.',
            ],
            [
                'carrier_code' => 'CARR-1005',
                'carrier_name' => 'South Carriers',
                'legal_name' => null,
                'sap_reference' => null,
                'street' => null,
                'postal_code' => null,
                'city' => 'Stuttgart',
                'country' => 'Germany',
                'primary_contact_name' => null,
                'contact_email' => null,
                'contact_phone' => null,
                'approval_state' => CarrierApprovalState::MISSING_DATA,
                'approved_for_loading' => false,
                'status' => CarrierStatus::ACTIVE,
                'is_sap_owned' => false,
                'is_active' => true,
                'notes' => 'Contact details and SAP reference missing.',
            ],
        ];

        foreach ($carriers as $carrier) {
            FreightForwarder::firstOrCreate(
                ['carrier_code' => $carrier['carrier_code']],
                array_merge(['id' => (string) Str::uuid()], $carrier)
            );
        }
    }
}
