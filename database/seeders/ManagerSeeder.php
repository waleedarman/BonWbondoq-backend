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
        $managerRole = Role::where('slug', Role::MANAGER)->firstOrFail();
        $branch = Branch::where('code', 'MAIN')->first();

        User::updateOrCreate(
            ['email' => env('MANAGER_EMAIL', 'manager@example.com')],
            [
                'name' => env('MANAGER_NAME', 'System Manager'),
                'phone' => env('MANAGER_PHONE'),
                'password' => Hash::make(env('MANAGER_PASSWORD', 'password')),
                'role_id' => $managerRole->id,
                'branch_id' => $branch?->id,
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => null,
            ]
        );
    }
}
