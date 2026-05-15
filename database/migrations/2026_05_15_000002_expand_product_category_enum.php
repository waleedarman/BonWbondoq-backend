<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $categories = collect(Product::CATEGORIES)
            ->map(fn (string $category) => "'{$category}'")
            ->implode(',');

        DB::statement("ALTER TABLE products MODIFY category ENUM({$categories}) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE products MODIFY category ENUM('raw_coffee','roasted_coffee','packaging_material','beverage','supply','other') NOT NULL");
    }
};
