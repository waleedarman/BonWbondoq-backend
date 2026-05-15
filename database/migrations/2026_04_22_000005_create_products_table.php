<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->enum('category', [
                'raw_coffee',
                'roasted_coffee',
                'ground_coffee',
                'raw_nuts',
                'roasted_nuts',
                'seasoned_nuts',
                'raw_product',
                'processed_product',
                'packaged_product',
                'packaging_material',
                'beverage',
                'supply',
                'other',
            ]);
            $table->enum('unit', ['kg', 'gram', 'piece', 'box', 'bottle', 'pack']);
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('minimum_quantity', 10, 2)->default(0);
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'category']);
            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
