<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Get the raw payload
        $payload = $request->getContent();

        // Verify the signature (very important for security)
        $secretKey = env('PAYSTACK_SECRET_KEY');
        $paystackSignature = $request->header('x-paystack-signature');

        if (!$paystackSignature) {
            Log::warning('Paystack webhook: Missing signature');
            return response()->json(['status' => 'missing signature'], 400);
        }

        // Compute expected signature
        $expectedSignature = hash_hmac('sha512', $payload, $secretKey);

        if (!hash_equals($expectedSignature, $paystackSignature)) {
            Log::warning('Paystack webhook: Invalid signature');
            return response()->json(['status' => 'invalid signature'], 400);
        }

        // Decode the event
        $event = json_decode($payload, true);

        if (!$event || !isset($event['event'])) {
            Log::warning('Paystack webhook: Invalid payload');
            return response()->json(['status' => 'invalid payload'], 400);
        }

        // Log the full event for debugging (remove in production if sensitive)
        Log::info('Paystack webhook received', ['event' => $event['event'], 'data' => $event['data']]);

        // Handle different events
        switch ($event['event']) {
            case 'charge.success':
                $this->handleSuccessfulCharge($event['data']);
                break;

            case 'charge.dispute.create':
            case 'charge.dispute.resolve':
            case 'transfer.success':
            case 'transfer.failed':
                // Handle other events as needed (disputes, transfers for payouts, etc.)
                Log::info('Paystack event received but not processed', ['event' => $event['event']]);
                break;

            default:
                Log::info('Unhandled Paystack event', ['event' => $event['event']]);
        }

        // Always return 200 to Paystack (they retry if not 200)
        return response()->json(['status' => 'success'], 200);
    }

    private function handleSuccessfulCharge($data)
    {
        $reference = $data['reference'];
        $amount = $data['amount'] / 100; // Paystack sends in kobo
        $email = $data['customer']['email'];
        $status = $data['status'];

        // Find the order by reference (you should have saved reference when initiating payment)
        $order = \App\Models\Order::where('paystack_reference', $reference)->first();

        if (!$order) {
            Log::warning('Paystack webhook: Order not found for reference', ['reference' => $reference]);
            return;
        }

        if ($order->payment_status === 'paid') {
            Log::info('Paystack webhook: Order already paid', ['reference' => $reference]);
            return;
        }

        // Mark order as paid
        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'transaction_data' => $data, // optional: store full response
        ]);

        // Additional actions
        // - Send order confirmation email
        // - Reduce product stock
        // - Notify vendor
        // - Create shipment record, etc.

        Log::info('Paystack webhook: Order marked as paid', [
            'order_id' => $order->id,
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }
}