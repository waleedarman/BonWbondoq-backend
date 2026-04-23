<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'location' => 'Main roastery branch',
                'is_active' => true,
            ]
        );
    }
}
