<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::MANAGER],
            [
                'name' => 'Manager',
                'description' => 'System manager',
            ],
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'location' => 'Main',
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager',
                'phone' => '0599000001',
                'password' => Hash::make('password'),
                'role_id' => $role->id,
                'branch_id' => $branch->id,
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => null,
            ],
        );
    }
}
