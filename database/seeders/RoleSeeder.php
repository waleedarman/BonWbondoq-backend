<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::MANAGER => 'Manager',
            Role::ROASTING_EMPLOYEE => 'Roasting Employee',
            Role::INVENTORY_EMPLOYEE => 'Inventory Employee',
            Role::DISTRIBUTION_EMPLOYEE => 'Distribution Employee',
        ];

        foreach ($roles as $slug => $name) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "{$name} system role.",
                ]
            );
        }
    }
}
