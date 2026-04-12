<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->vendor) {
            return response()->json(['error' => 'Vendor profile not found'], 404);
        }

        $vendor = $user->vendor;

        $stats = [
            'totalOrders'      => $vendor->orders()->count(),
            'totalSales'       => 1245000,
            'pendingOrders'    => $vendor->orders()->where('status', 'pending')->count(),
            'revenueThisMonth' => 458000,
        ];

        $recentOrders = $vendor->orders()
            ->with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number ?? 'ORD-'.$order->id,
                'customer' => $order->user->name ?? 'Unknown',
                'total' => $order->total_amount ?? 0,
                'status' => $order->status,
                'date' => $order->created_at->format('Y-m-d'),
            ]);

        return response()->json([
            'stats' => $stats,
            'recentOrders' => $recentOrders,
        ]);
    }
}