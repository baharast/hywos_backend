<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate([
            'email' => 'admin@local'
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('password')
        ]);

        $role = Role::firstWhere('name', 'admin');
        if ($role) {
            $user->assignRole($role);
        }
    }
}
