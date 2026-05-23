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
                'username' => 'admin',
                'email' => 'admin@local',
                'name' => 'Admin User',
                'phone' => null,
                'preferred_language' => 'de',
                'role' => 'admin',
            ],
            [
                'username' => 'dispatcher',
                'email' => 'dispatcher@local',
                'name' => 'Dispatcher User',
                'phone' => null,
                'preferred_language' => 'de',
                'role' => 'dispatcher',
            ],
            [
                'username' => 'operator',
                'email' => 'operator@local',
                'name' => 'Operator User',
                'phone' => null,
                'preferred_language' => 'de',
                'role' => 'operator',
            ],
            [
                'username' => 'auditor',
                'email' => 'auditor@local',
                'name' => 'Auditor User',
                'phone' => null,
                'preferred_language' => 'de',
                'role' => 'auditor',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'name' => $userData['name'],
                    'phone' => $userData['phone'],
                    'preferred_language' => $userData['preferred_language'],
                    'password' => bcrypt('password'),
                    'is_active' => true,
                    'is_locked' => false,
                ]
            );

            $user->update([
                'username' => $userData['username'],
                'preferred_language' => $userData['preferred_language'],
                'is_active' => true,
                'is_locked' => false,
            ]);

            $role = Role::firstWhere('name', $userData['role']);
            if ($role) {
                $user->assignRole($role);
            }
        }
    }
}
