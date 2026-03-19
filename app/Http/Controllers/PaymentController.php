<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function verifyPaystack(Request $request)
    {
        $request->validate(['reference' => 'required|string']);

        $secretKey = env('PAYSTACK_SECRET_KEY');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->get("https://api.paystack.co/transaction/verify/{$request->reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            // Payment verified!
            // Create order, update status, send email, etc.
            // Example:
            // Order::create([...]);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified',
                'data' => $response->json('data'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed',
        ], 400);
    }
}