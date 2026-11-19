<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('payment_provider', 50)->default('stripe');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->enum('payment_status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
