<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });

        Schema::table('coupon_user', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('coupon_user', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
    }
};
