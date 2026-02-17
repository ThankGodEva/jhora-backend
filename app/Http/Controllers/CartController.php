<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $items = CartItem::where('user_id', $user->id)
            ->with('product.vendor')
            ->get()
            ->map(function ($item) {
                $product = $item->product;
                return [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->price,
                    'image' => $product->images->first()?->image_path ?? 'https://via.placeholder.com/100',
                    'quantity' => $item->quantity,
                    'vendor' => $product->vendor->shop_name ?? 'Unknown',
                ];
            });

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $item = CartItem::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $request->product_id],
            ['quantity' => $request->quantity]
        );

        return response()->json(['message' => 'Added to cart', 'item' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['quantity' => 'required|integer|min:1']);

        $item = CartItem::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $item->update(['quantity' => $request->quantity]);

        return response()->json($item);
    }

    public function destroy($id)
    {
        CartItem::where('id', $id)->where('user_id', Auth::id())->delete();
        return response()->json(['message' => 'Removed from cart']);
    }

    public function clear()
    {
        CartItem::where('user_id', Auth::id())->delete();
        return response()->json(['message' => 'Cart cleared']);
    }
}