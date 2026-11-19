<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('password');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('account_type', ['customer', 'staff'])->default('customer')->after('avatar');
            $table->boolean('is_active')->default(true)->after('account_type');

            $table->index('account_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['phone', 'avatar', 'account_type', 'is_active']);
        });
    }
};
