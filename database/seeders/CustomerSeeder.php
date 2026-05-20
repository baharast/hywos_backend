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
                'email' => 'customer1@example.local',
                'phone' => '+10000000000',
            ],
            [
                'code' => 'CUST-2',
                'name' => 'Second Customer',
                'email' => 'customer2@example.local',
                'phone' => '+10000000001',
            ],
            [
                'code' => 'CUST-3',
                'name' => 'Third Customer',
                'email' => 'customer3@example.local',
                'phone' => '+10000000002',
            ],
            [
                'code' => 'CUST-4',
                'name' => 'Fourth Customer',
                'email' => 'customer4@example.local',
                'phone' => '+10000000003',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate([
                'code' => $customer['code'],
            ], [
                'id' => (string) Str::uuid(),
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'is_active' => true,
            ]);
        }
    }
}
