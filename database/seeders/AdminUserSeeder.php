<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Demo users for terminal Manager Logon (V3 §11) + dashboard testing.
 *
 * One user per role from FillTrack Roles & Permissions V1 §2.1, plus two
 * users in error states (locked + disabled) so the FE can exercise the
 * §11.3 error paths without manual DB tweaks.
 *
 * Credentials are deliberately memorable (username == role, password ==
 * "<role>12345"). This seeder is for the DEMO environment only — production
 * users must NEVER reuse these credentials.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // Active demo users — one per role
            [
                'username' => 'admin',
                'password' => 'admin12345',
                'email' => 'admin@local',
                'name' => 'Admin User',
                'role' => 'admin',
                'preferred_language' => 'de',
            ],
            [
                'username' => 'operations',
                'password' => 'operations12345',
                'email' => 'operations@local',
                'name' => 'Operations Manager User',
                'role' => 'operations_manager',
                'preferred_language' => 'de',
            ],
            [
                'username' => 'dispatcher',
                'password' => 'dispatcher12345',
                'email' => 'dispatcher@local',
                'name' => 'Dispatcher User',
                'role' => 'dispatcher_manager',
                'preferred_language' => 'de',
            ],
            [
                'username' => 'operator',
                'password' => 'operator12345',
                'email' => 'operator@local',
                'name' => 'Operator User',
                'role' => 'operator',
                'preferred_language' => 'de',
            ],
            [
                'username' => 'analysis',
                'password' => 'analysis12345',
                'email' => 'analysis@local',
                'name' => 'Analysis Specialist',
                'role' => 'analysis_specialist',
                'preferred_language' => 'de',
            ],
            [
                'username' => 'support',
                'password' => 'support12345',
                'email' => 'support@local',
                'name' => 'IT / Support User',
                'role' => 'it_support',
                'preferred_language' => 'de',
            ],
            [
                'username' => 'auditor',
                'password' => 'auditor12345',
                'email' => 'auditor@local',
                'name' => 'Auditor User',
                'role' => 'auditor',
                'preferred_language' => 'de',
            ],
            // Error-path users for V3 §11.3 testing
            [
                'username' => 'lockeduser',
                'password' => 'locked12345',
                'email' => 'locked@local',
                'name' => 'Locked Operator (demo)',
                'role' => 'operator',
                'preferred_language' => 'de',
                'is_locked' => true,
                'locked_reason' => 'Demo seed — locked for ACCOUNT_LOCKED test path.',
                'locked_at' => now()->subHours(2),
            ],
            [
                'username' => 'disableduser',
                'password' => 'disabled12345',
                'email' => 'disabled@local',
                'name' => 'Disabled Operator (demo)',
                'role' => 'operator',
                'preferred_language' => 'de',
                'is_active' => false,
                'disabled_at' => now()->subDays(1),
                'disabled_reason' => 'Demo seed — disabled for ACCOUNT_DISABLED test path.',
            ],
        ];

        foreach ($users as $row) {
            $isLocked = $row['is_locked'] ?? false;
            $isActive = $row['is_active'] ?? true;

            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'username' => $row['username'],
                    'name' => $row['name'],
                    'phone' => null,
                    'preferred_language' => $row['preferred_language'],
                    'password' => Hash::make($row['password']),
                    'is_active' => $isActive,
                    'is_locked' => $isLocked,
                ]
            );

            // Re-run friendly: snap everything back to seeded state and
            // refresh the password (Hash::make is non-deterministic, so we
            // only update when the seed value can't auth — keeps re-seeds
            // cheap on big projects but correct on demo).
            $update = [
                'username' => $row['username'],
                'name' => $row['name'],
                'preferred_language' => $row['preferred_language'],
                'is_active' => $isActive,
                'is_locked' => $isLocked,
                'locked_at' => $row['locked_at'] ?? null,
                'locked_reason' => $row['locked_reason'] ?? null,
                'disabled_at' => $row['disabled_at'] ?? null,
                'disabled_reason' => $row['disabled_reason'] ?? null,
            ];
            if (! Hash::check($row['password'], $user->password)) {
                $update['password'] = Hash::make($row['password']);
            }
            $user->update($update);

            $role = Role::firstWhere('name', $row['role']);
            if ($role) {
                $user->syncRoles([$role]);
            }
        }
    }
}
