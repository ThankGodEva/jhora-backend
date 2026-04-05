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
            return response()->json([
                'error' => 'Vendor profile not found'
            ], 404);
        }

        $vendor = $user->vendor;

        $stats = [
            'totalOrders'      => 24,
            'totalSales'       => 1245000,
            'pendingOrders'    => 7,
            'revenueThisMonth' => 458000,
        ];

        $recentOrders = [
            [
                'id'           => 1,
                'order_number' => 'ORD-20260318-001',
                'customer'     => 'Chibueze ThankGod',
                'total'        => 45000,
                'status'       => 'pending',
                'date'         => '2026-03-18',
            ],
        ];

        return response()->json([
            'stats'        => $stats,
            'recentOrders' => $recentOrders,
        ]);
    }
}