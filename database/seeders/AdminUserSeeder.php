<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'admin@local',
                'name' => 'Admin User',
                'role' => 'admin',
            ],
            [
                'email' => 'dispatcher@local',
                'name' => 'Dispatcher User',
                'role' => 'dispatcher',
            ],
            [
                'email' => 'operator@local',
                'name' => 'Operator User',
                'role' => 'operator',
            ],
            [
                'email' => 'auditor@local',
                'name' => 'Auditor User',
                'role' => 'auditor',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt('password'),
                ]
            );

            $role = Role::firstWhere('name', $userData['role']);
            if ($role) {
                $user->assignRole($role);
            }
        }
    }
}
