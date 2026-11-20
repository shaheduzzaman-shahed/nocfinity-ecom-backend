<?php

use App\Http\Controllers\Api\V1\Payment\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->group(function () {
    // Stripe publishable config for frontend elements
    Route::get('/stripe/config', [StripeWebhookController::class, 'config']);

    // Public Stripe Webhook Receiver
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
});
