<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'code' => 'MAIN',
                'name' => 'Main Branch',
                'location' => 'City Center Roastery',
                'is_active' => true,
            ],
            [
                'code' => 'NORTH',
                'name' => 'North Branch',
                'location' => 'Northside Operations Hub',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }
    }
}
