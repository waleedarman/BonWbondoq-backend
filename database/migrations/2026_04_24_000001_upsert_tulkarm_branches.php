<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            [
                'code' => 'MAIN',
                'name' => 'طولكرم وسط البلد',
                'location' => 'وسط البلد',
            ],
            [
                'code' => 'NORTH',
                'name' => 'طولكرم - شارع نابلس',
                'location' => 'شارع نابلس',
            ],
            [
                'code' => 'ATTIL',
                'name' => 'طولكرم- عتيل',
                'location' => 'عتيل',
            ],
        ] as $branch) {
            DB::table('branches')->updateOrInsert(
                ['code' => $branch['code']],
                [
                    'name' => $branch['name'],
                    'location' => $branch['location'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('branches')
            ->whereIn('code', ['MAIN', 'NORTH', 'ATTIL'])
            ->delete();
    }
};
