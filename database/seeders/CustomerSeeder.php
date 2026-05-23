<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'code' => 'CUST-1',
                'name' => 'Sample Customer',
                'legal_name' => 'Sample Customer GmbH',
                'sap_customer_no' => 'SAP-CUST-1001',
                'email' => 'customer1@example.local',
                'phone' => '+10000000000',
                'primary_contact_name' => 'Anna Schmidt',
                'city' => 'Schweinfurt',
                'country' => 'Germany',
                'default_document_language' => 'de',
                'document_requirements' => ['certificate', 'delivery_note'],
                'status' => 'active',
            ],
            [
                'code' => 'CUST-2',
                'name' => 'Second Customer',
                'legal_name' => 'Second Customer AG',
                'sap_customer_no' => 'SAP-CUST-1002',
                'email' => 'customer2@example.local',
                'phone' => '+10000000001',
                'primary_contact_name' => 'Klaus Müller',
                'city' => 'Munich',
                'country' => 'Germany',
                'default_document_language' => 'de',
                'document_requirements' => ['certificate'],
                'status' => 'active',
            ],
            [
                'code' => 'CUST-3',
                'name' => 'Third Customer',
                'legal_name' => null,
                'sap_customer_no' => null,
                'email' => 'customer3@example.local',
                'phone' => '+10000000002',
                'primary_contact_name' => null,
                'city' => 'Frankfurt',
                'country' => 'Germany',
                'default_document_language' => 'de',
                'document_requirements' => [],
                'status' => 'inactive',
            ],
            [
                'code' => 'CUST-4',
                'name' => 'Fourth Customer',
                'legal_name' => 'Fourth Customer Ltd.',
                'sap_customer_no' => 'SAP-CUST-1004',
                'email' => 'customer4@example.local',
                'phone' => '+10000000003',
                'primary_contact_name' => 'Sarah Becker',
                'city' => 'Berlin',
                'country' => 'Germany',
                'default_document_language' => 'en',
                'document_requirements' => ['certificate', 'delivery_note', 'qm_document'],
                'status' => 'active',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(
                ['code' => $customer['code']],
                array_merge(
                    ['id' => (string) Str::uuid(), 'is_active' => $customer['status'] === 'active'],
                    $customer
                )
            );
        }
    }
}
