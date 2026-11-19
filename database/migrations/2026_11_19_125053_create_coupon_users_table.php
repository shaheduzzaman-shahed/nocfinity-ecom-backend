<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // order_id FK will be added when orders table is created
            $table->unsignedBigInteger('order_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user');
    }
};
