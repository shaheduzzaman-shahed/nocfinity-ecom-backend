<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripePaymentService $stripePaymentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature') ?? '';

        try {
            $event = $this->stripePaymentService->verifyWebhookSignature($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            Log::warning("Stripe webhook signature verification failed: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 400);
        } catch (\Throwable $e) {
            Log::error("Stripe webhook parsing error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Error parsing webhook event.',
            ], 400);
        }

        $eventType = $event->type ?? '';
        $dataObject = $event->data->object ?? null;

        if (! $dataObject) {
            return response()->json([
                'success' => true,
                'message' => 'Empty event data object.',
            ]);
        }

        switch ($eventType) {
            case 'payment_intent.succeeded':
                $paymentIntentId = $dataObject->id;
                $chargeId = $dataObject->latest_charge ?? null;
                $metadata = is_object($dataObject->metadata)
                    ? (array) $dataObject->metadata
                    : ($dataObject->metadata ?? []);

                try {
                    $this->stripePaymentService->handlePaymentSucceeded(
                        $paymentIntentId,
                        $chargeId,
                        $metadata,
                    );
                } catch (\Throwable $e) {
                    Log::error("Error handling payment_intent.succeeded: {$e->getMessage()}");

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 500);
                }
                break;

            case 'payment_intent.payment_failed':
                $paymentIntentId = $dataObject->id;
                $failureReason = $dataObject->last_payment_error?->message ?? 'Payment failed.';

                try {
                    $this->stripePaymentService->handlePaymentFailed($paymentIntentId, $failureReason);
                } catch (\Throwable $e) {
                    Log::error("Error handling payment_intent.payment_failed: {$e->getMessage()}");
                }
                break;

            default:
                Log::info("Unhandled Stripe webhook event: {$eventType}");
                break;
        }

        return response()->json([
            'success' => true,
            'message' => "Stripe webhook event {$eventType} processed.",
        ]);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'publishable_key' => config('services.stripe.key'),
            ],
        ]);
    }
}
