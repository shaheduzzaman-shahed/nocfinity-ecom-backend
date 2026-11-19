<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_address_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('order_status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->char('currency', 3);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('product_discount', 10, 2)->default(0.00);
            $table->decimal('coupon_discount', 10, 2)->default(0.00);
            $table->decimal('shipping_charge', 10, 2)->default(0.00);
            $table->decimal('final_total', 10, 2);
            $table->string('ship_name');
            $table->string('ship_email');
            $table->string('ship_phone', 50);
            $table->text('ship_address');
            $table->string('ship_city', 100);
            $table->string('ship_state', 100);
            $table->string('ship_postal_code', 20);
            $table->string('ship_country', 100);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('order_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
