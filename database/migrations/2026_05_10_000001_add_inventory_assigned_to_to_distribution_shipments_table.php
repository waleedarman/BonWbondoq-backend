<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_shipments', function (Blueprint $table): void {
            $table->foreignId('inventory_assigned_to')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->index(['inventory_assigned_to', 'status'], 'distribution_shipments_inventory_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_shipments', function (Blueprint $table): void {
            $table->dropIndex('distribution_shipments_inventory_status_index');
            $table->dropConstrainedForeignId('inventory_assigned_to');
        });
    }
};