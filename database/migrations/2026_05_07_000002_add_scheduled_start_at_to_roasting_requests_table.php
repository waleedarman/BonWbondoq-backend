<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('roasting_requests', 'scheduled_start_at')) {
            return;
        }

        Schema::table('roasting_requests', function (Blueprint $table): void {
            $table->timestamp('scheduled_start_at')->nullable()->after('branch_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('roasting_requests', 'scheduled_start_at')) {
            return;
        }

        Schema::table('roasting_requests', function (Blueprint $table): void {
            $table->dropColumn('scheduled_start_at');
        });
    }
};
