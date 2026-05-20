<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::firstOrCreate([
            'code' => 'CUST-1'
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Sample Customer',
            'email' => 'customer@example.local',
            'phone' => '+10000000000',
            'is_active' => true,
        ]);
    }
}
