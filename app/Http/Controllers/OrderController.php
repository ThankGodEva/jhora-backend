<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shipping_address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();
        $cartItems = CartItem::where('user_id', $user->id)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        $subtotal = $cartItems->sum(fn($item) => $item->quantity * $item->product->price);
        $shipping = 5000; // hardcoded for now
        $total = $subtotal + $shipping;

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'JH-' . Str::random(8),
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping,
            'total_amount' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'vendor_id' => $item->product->vendor_id,
                'product_name' => $item->product->name,
                'unit_price' => $item->product->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->quantity * $item->product->price,
            ]);
        }

        // Clear cart after order
        CartItem::where('user_id', $user->id)->delete();

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order,
        ], 201);
    }

    public function show($order_number)
    {
        $order = Order::where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->with('orderItems.product')
            ->firstOrFail();

        return response()->json($order);
    }
}