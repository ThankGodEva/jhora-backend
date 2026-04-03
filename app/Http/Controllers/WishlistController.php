<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        $wishlist = auth()->user()->wishlist()->with('product')->get();
        return response()->json($wishlist->pluck('product'));
    }

    public function store(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        Wishlist::firstOrCreate([
            'user_id' => auth()->id(),
            'product_id' => $request->product_id,
        ]);

        return response()->json(['message' => 'Added to wishlist'], 201);
    }

    public function destroy($id)
    {
        Wishlist::where('user_id', auth()->id())
                ->where('product_id', $id)
                ->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }

    public function clear()
    {
        Wishlist::where('user_id', auth()->id())->delete();
        return response()->json(['message' => 'Wishlist cleared']);
    }
}