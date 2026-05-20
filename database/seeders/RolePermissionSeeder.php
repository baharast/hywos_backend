<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $roles = ['admin', 'dispatcher', 'operator', 'auditor'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // Permissions
        $permissions = [
            'baylines.view', 'baylines.create', 'baylines.update', 'baylines.delete',
            'parkings.view', 'parkings.create', 'parkings.update', 'parkings.delete',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Assign all permissions to admin
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }
    }
}

