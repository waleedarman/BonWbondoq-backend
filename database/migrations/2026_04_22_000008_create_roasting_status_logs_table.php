<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roasting_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roasting_request_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled']);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['roasting_request_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roasting_status_logs');
    }
};
