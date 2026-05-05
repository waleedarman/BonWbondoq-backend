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
                'name' => 'طولكرم وسط البلد',
                'location' => 'وسط البلد',
                'is_active' => true,
            ],
            [
                'code' => 'NORTH',
                'name' => 'طولكرم - شارع نابلس',
                'location' => 'شارع نابلس',
                'is_active' => true,
            ],
            [
                'code' => 'ATTIL',
                'name' => 'طولكرم- عتيل',
                'location' => 'عتيل',
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
