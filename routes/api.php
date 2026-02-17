<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;

Route::post('/register/check-email', [RegisterController::class, 'checkEmail']);
Route::post('/register', [RegisterController::class, 'register']);

Route::post('/register/icreator', [RegisterController::class, 'registerICreator']);

// Route::middleware('auth:sanctum')->prefix('vendor/setup')->group(function () {
//     Route::post('/business', [RegisterController::class, 'setupBusiness']);
// });

Route::middleware('auth:sanctum')->prefix('vendor/setup')->group(function () {
    Route::post('/business', [RegisterController::class, 'setupBusiness']);
    // Route::post('/business', function (Request $request) {
    //     // Save business info to Vendor model
    //     return response()->json(['message' => 'Business info saved']);
    // });

    Route::post('/shipping', function (Request $request) {
        // Save shipping info
        return response()->json(['message' => 'Shipping info saved']);
    });
});

// routes/api.php
Route::middleware('auth:sanctum')->get('/notifications', function () {
    // Replace with real query later
    return response()->json([
        // Your notification data
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json(['message' => 'Backend is alive!']);
});

Route::get('/products', function () {
    return \App\Models\Product::where('is_featured', true)
        ->with('vendor') // get shop name
        ->get()
        ->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => '₦' . number_format($product->price),
                'slug' => $product->slug,
                'image' => $product->images->first()?->image_path ?? 'https://via.placeholder.com/300',
                'vendor' => ['shop_name' => $product->vendor?->shop_name ?? 'Unknown'],
            ];
        });
});

Route::get('/products/featured', function () {
    $products = \App\Models\Product::where('is_featured', true)
        ->with(['vendor', 'images']) // ← Add 'images' here!
        ->take(8)
        ->get()
        ->map(function ($product) {
            $firstImage = $product->images->first(); // safe now
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => '₦' . number_format($product->price),
                'compare_price' => $product->compare_price ? '₦' . number_format($product->compare_price) : null,
                'image' => $firstImage?->image_path ?? 'https://via.placeholder.com/300x300',
                'vendor' => $product->vendor?->shop_name ?? 'Unknown Creator',
                'rating' => 4.8, // placeholder
            ];
        });

    return response()->json($products);
});

Route::get('/products/{slug}', function ($slug) {
    $product = \App\Models\Product::where('slug', $slug)
        ->with(['vendor', 'images', 'variations'])
        ->firstOrFail();

    $colors = $product->variations
        ->where('variation_type', 'color')
        ->pluck('variation_value')
        ->unique()
        ->toArray();

    $sizes = $product->variations
        ->where('variation_type', 'size')
        ->pluck('variation_value')
        ->unique()
        ->toArray();

    return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        'slug' => $product->slug,
        'description' => $product->description,
        'price' => '₦' . number_format($product->price),
        'compare_price' => $product->compare_price ? '₦' . number_format($product->compare_price) : null,
        'stock_quantity' => $product->stock_quantity,
        'availability' => $product->stock_quantity > 0 ? 'In stock' : 'Out of stock',
        'images' => $product->images->pluck('image_path')->toArray(),
        'colors' => $product->variations->where('variation_type', 'color')->pluck('variation_value')->unique()->toArray(),
        'sizes' => $product->variations->where('variation_type', 'size')->pluck('variation_value')->unique()->toArray(),
        'vendor' => [
            'shop_name' => $product->vendor->shop_name,
            'slug' => $product->vendor->slug,
        ],
        'rating' => 4.8, // placeholder
        'reviews_count' => 236, // placeholder
    ]);
});

// Cart routes
Route::prefix('cart')->group(function () {
    Route::get('/', [App\Http\Controllers\CartController::class, 'index']);
    Route::post('/', [App\Http\Controllers\CartController::class, 'store']);
    Route::put('/{id}', [App\Http\Controllers\CartController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\CartController::class, 'destroy']);
    Route::delete('/', [App\Http\Controllers\CartController::class, 'clear']);
});

// Checkout / Orders
Route::post('/orders', [App\Http\Controllers\OrderController::class, 'store']);
Route::get('/orders/{order_number}', [App\Http\Controllers\OrderController::class, 'show']);


// List all vendors (for /icreators page later)
Route::get('/icreators', function () {
    $vendors = \App\Models\Vendor::where('is_active', true)
        ->where('verification_status', 'verified')
        ->get()
        ->map(fn($v) => [
            'id' => $v->id,
            'shop_name' => $v->shop_name,
            'slug' => $v->slug,
            'description' => $v->description,
            'logo' => $v->logo,
            'banner' => $v->banner,
            'rating' => 4.8, // placeholder
            'products_count' => $v->products()->count(),
        ]);

    return response()->json($vendors);
});

// Single vendor detail
Route::get('/icreators/{slug}', function ($slug) {
    $vendor = \App\Models\Vendor::where('slug', $slug)
        ->where('is_active', true)
        ->where('verification_status', 'verified')
        ->firstOrFail();

    return response()->json([
        'id' => $vendor->id,
        'shop_name' => $vendor->shop_name,
        'slug' => $vendor->slug,
        'description' => $vendor->description,
        'logo' => $vendor->logo ?? 'https://via.placeholder.com/160',
        'banner' => $vendor->banner ?? 'https://via.placeholder.com/1200x400',
        'rating' => 4.8,
        'products_count' => $vendor->products()->count(),
    ]);
});

// Products for a specific vendor
Route::get('/icreators/{slug}/products', function ($slug) {
    $vendor = \App\Models\Vendor::where('slug', $slug)->firstOrFail();

    $products = $vendor->products()
        ->where('status', 'published')
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => '₦' . number_format($p->price),
            'image' => $p->images->first()?->image_path ?? 'https://via.placeholder.com/300',
            'is_featured' => $p->is_featured,
        ]);

    return response()->json($products);
});