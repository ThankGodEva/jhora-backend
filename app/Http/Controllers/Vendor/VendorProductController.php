<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorProductController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json(['error' => 'Vendor profile not found'], 404);
        }

        $products = $vendor->products()
            ->with('images')
            ->latest()
            ->get();

        $formatted = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock_quantity ?? 0,
                'image' => $product->images->first()?->image_path ?? '/placeholder.jpg',
                'status' => $product->status ?? 'active',
                'sales' => $product->orderItems()->count(),
            ];
        });

        return response()->json($formatted);
    }
}