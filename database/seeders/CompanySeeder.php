<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'id' => (string) Str::uuid(),
                'code' => 'TYCZKA',
                'name' => 'Tyczka Hydrogen GmbH',
                'legal_name' => 'Tyczka Hydrogen GmbH',
                'description' => 'Demo hydrogen supplier company',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'COMP-002',
                'name' => 'Global Energy Solutions',
                'legal_name' => 'Global Energy Solutions Ltd.',
                'description' => 'Demo energy company',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'COMP-003',
                'name' => 'Industrial Gas Services',
                'legal_name' => 'Industrial Gas Services Inc.',
                'description' => 'Demo industrial gas company',
                'is_active' => true,
            ],
        ];

        foreach ($companies as $company) {
            Company::create($company);
        }
    }
}
