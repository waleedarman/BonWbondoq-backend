<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('distribution_shipments', 'inventory_assigned_to')) {
                $table->foreignId('inventory_assigned_to')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index(['inventory_assigned_to', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('distribution_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('distribution_shipments', 'inventory_assigned_to')) {
                $table->dropForeign(['inventory_assigned_to']);
                $table->dropIndex(['inventory_assigned_to', 'status']);
                $table->dropColumn('inventory_assigned_to');
            }
        });
    }
};
