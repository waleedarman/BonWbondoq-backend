<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->foreignId('branch_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(false)->after('role_id');
            $table->timestamp('approved_at')->nullable()->after('is_active');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['role_id']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'phone',
                'branch_id',
                'role_id',
                'is_active',
                'approved_at',
                'approved_by',
            ]);
        });
    }
};
