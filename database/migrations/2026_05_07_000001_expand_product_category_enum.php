<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $categories = collect(Product::CATEGORIES)
            ->map(fn (string $category): string => "'{$category}'")
            ->implode(',');

        DB::statement("ALTER TABLE products MODIFY category ENUM({$categories}) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $legacyCategories = [
            'raw_coffee',
            'roasted_coffee',
            'packaging_material',
            'beverage',
            'supply',
            'other',
        ];

        DB::table('products')
            ->whereNotIn('category', $legacyCategories)
            ->update(['category' => 'other']);

        $categories = collect($legacyCategories)
            ->map(fn (string $category): string => "'{$category}'")
            ->implode(',');

        DB::statement("ALTER TABLE products MODIFY category ENUM({$categories}) NOT NULL");
    }
};
