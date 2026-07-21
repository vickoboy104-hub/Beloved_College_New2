<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Setting;
use App\Services\Payments\PaymentCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebhookController extends Controller
{
    public function paystack(Request $request, PaymentCheckoutService $checkout): JsonResponse
    {
        $secret = Setting::getValue('paystack_webhook_secret') ?: Setting::getValue('paystack_secret_key');
        $signature = (string) $request->header('x-paystack-signature');
        $expected = $secret ? hash_hmac('sha512', $request->getContent(), $secret) : null;

        if (! $expected || ! $signature || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $eventType = (string) $request->input('event');
        $reference = (string) $request->input('data.reference');
        $event = $this->event(
            PaymentProvider::Paystack,
            (string) ($request->input('data.id') ?: $eventType.':'.$reference),
            $eventType,
            $reference,
            $signature,
            $request->all(),
        );

        if ($event->processed_at) {
            return response()->json(['received' => true]);
        }

        if ($eventType !== 'charge.success') {
            $this->completeEvent($event, 'ignored');

            return response()->json(['received' => true]);
        }

        return $this->confirmEvent($event, PaymentProvider::Paystack, $reference, [], $checkout);
    }

    public function flutterwave(Request $request, PaymentCheckoutService $checkout): JsonResponse
    {
        $secretHash = Setting::getValue('flutterwave_secret_hash');
        $signature = (string) $request->header('flutterwave-signature');
        $expected = $secretHash
            ? base64_encode(hash_hmac('sha256', $request->getContent(), $secretHash, true))
            : null;

        if (! $expected || ! $signature || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $reference = (string) ($request->input('data.tx_ref') ?: $request->input('tx_ref'));
        $transactionId = $request->input('data.id') ?: $request->input('id');
        $event = $this->event(
            PaymentProvider::Flutterwave,
            (string) ($transactionId ?: 'flutterwave:'.$reference),
            (string) ($request->input('type') ?: $request->input('event')),
            $reference,
            $signature,
            $request->all(),
        );

        if ($event->processed_at) {
            return response()->json(['received' => true]);
        }

        return $this->confirmEvent(
            $event,
            PaymentProvider::Flutterwave,
            $reference,
            ['transaction_id' => $transactionId],
            $checkout,
        );
    }

    public function monnify(Request $request, PaymentCheckoutService $checkout): JsonResponse
    {
        $secret = Setting::getValue('monnify_secret_key');
        $signature = (string) $request->header('monnify-signature');
        $expected = $secret ? hash_hmac('sha512', $request->getContent(), $secret) : null;
        $environment = Setting::getValue('monnify_environment', 'sandbox');

        if ($environment === 'live') {
            if (! $expected || ! $signature || ! hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } elseif ($signature !== '' && (! $expected || ! hash_equals($expected, $signature))) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $reference = (string) ($request->input('eventData.paymentReference') ?: $request->input('paymentReference'));
        $transactionReference = (string) ($request->input('eventData.transactionReference') ?: $request->input('transactionReference'));
        $event = $this->event(
            PaymentProvider::Monnify,
            $transactionReference ?: 'monnify:'.$reference,
            (string) $request->input('eventType'),
            $reference,
            $signature,
            $request->all(),
        );

        if ($event->processed_at) {
            return response()->json(['received' => true]);
        }

        return $this->confirmEvent($event, PaymentProvider::Monnify, $reference, [], $checkout);
    }

    public function palmpay(): JsonResponse
    {
        return response()->json([
            'message' => 'PalmPay merchant-specific webhook verification is not configured. No payment was updated.',
        ], 503);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(
        PaymentProvider $provider,
        string $eventId,
        string $eventType,
        string $reference,
        string $signature,
        array $payload,
    ): PaymentEvent {
        $payment = Payment::query()
            ->where('reference', $reference)
            ->where('provider', $provider->value)
            ->first();

        return PaymentEvent::query()->firstOrCreate(
            [
                'provider' => $provider->value,
                'event_id' => $eventId,
            ],
            [
                'payment_id' => $payment?->id,
                'event_type' => $eventType,
                'payment_reference' => $reference,
                'signature_hash' => $signature !== '' ? hash('sha256', $signature) : null,
                'status' => 'received',
                'payload' => $payload,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function confirmEvent(
        PaymentEvent $event,
        PaymentProvider $provider,
        string $reference,
        array $context,
        PaymentCheckoutService $checkout,
    ): JsonResponse {
        if ($reference === '') {
            $this->failEvent($event, 'Missing payment reference.');

            return response()->json(['message' => 'Payment reference not found'], 422);
        }

        try {
            $payment = $checkout->confirm($provider, $reference, $context);
            $event->update(['payment_id' => $payment->id]);

            if ($payment->status !== PaymentStatus::Paid) {
                $this->failEvent($event, 'Authoritative verification did not match the expected payment.');

                return response()->json(['message' => 'Payment verification mismatch'], 422);
            }

            $this->completeEvent($event, 'processed');

            return response()->json(['received' => true]);
        } catch (Throwable $exception) {
            report($exception);
            $this->failEvent($event, 'Payment verification is temporarily unavailable.');

            return response()->json(['message' => 'Payment verification is temporarily unavailable'], 503);
        }
    }

    private function completeEvent(PaymentEvent $event, string $status): void
    {
        $event->update([
            'status' => $status,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    private function failEvent(PaymentEvent $event, string $message): void
    {
        $event->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
