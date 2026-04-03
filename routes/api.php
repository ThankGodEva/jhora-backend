<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::post('/register/check-email', [RegisterController::class, 'checkEmail']);
Route::post('/register', [RegisterController::class, 'register']);

Route::post('/register/icreator', [RegisterController::class, 'registerICreator']);

// Route::middleware('auth:sanctum')->prefix('vendor/setup')->group(function () {
//     Route::post('/business', [RegisterController::class, 'setupBusiness']);
// });

Route::middleware('auth:sanctum')->prefix('vendor')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json(['error' => 'Vendor profile not found'], 404);
        }

        $stats = [
            'totalOrders' => $vendor->orderItems()->count(),
            'totalSales' => $vendor->orderItems()->sum('subtotal'),
            'pendingOrders' => $vendor->orderItems()->whereHas('order', fn($q) => $q->where('status', 'pending'))->count(),
            'revenue' => $vendor->orderItems()->whereHas('order', fn($q) => $q->where('status', 'completed'))->sum('subtotal'),
        ];

        $recentOrders = $vendor->orderItems()
            ->with('order.user')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'id' => $item->order->id,
                'order_number' => $item->order->order_number,
                'customer_name' => $item->order->user->name,
                'date' => $item->created_at->format('M d, Y'),
                'total' => $item->subtotal,
                'items' => $item->quantity,
                'status' => $item->order->status,
            ]);

        return response()->json([
            'stats' => $stats,
            'recentOrders' => $recentOrders,
        ]);
    });
});

Route::middleware('auth:sanctum')->prefix('vendor')->group(function () {
    Route::get('/analytics', function (Request $request) {
        $user = $request->user();
        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json(['error' => 'Vendor profile not found'], 404);
        }

        $range = $request->query('range', 'this_month');
        $startDate = now()->startOfMonth();
        if ($range === 'today') $startDate = now()->startOfDay();
        if ($range === 'this_week') $startDate = now()->startOfWeek();
        if ($range === 'last_3_months') $startDate = now()->subMonths(3)->startOfMonth();

        $salesData = $vendor->orderItems()
            ->whereHas('order', fn($q) => $q->where('created_at', '>=', $startDate))
            ->selectRaw('DATE(created_at) as date, SUM(subtotal) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $stats = [
            'totalSales' => $vendor->orderItems()->whereHas('order', fn($q) => $q->where('status', 'completed'))->sum('subtotal'),
            'totalOrders' => $vendor->orderItems()->count(),
            'avgOrderValue' => $vendor->orderItems()->avg('subtotal') ?? 0,
        ];

        $topProducts = $vendor->products()
            ->withCount('orderItems')
            ->orderByDesc('order_items_count')
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'name' => $p->name,
                'price' => $p->price,
                'sales' => $p->order_items_count,
                'growth' => rand(5, 25), // placeholder
                'image' => $p->images->first()?->image_path ?? null,
            ]);

        // ... similar for topCategories, customerMetrics ...

        return response()->json([
            'salesData' => $salesData,
            'stats' => $stats,
            'topProducts' => $topProducts,
            // add topCategories, customerMetrics when ready
        ]);
    });
});

Route::middleware('auth:sanctum')->prefix('vendor')->group(function () {
    // List orders (filter by status)
    Route::get('/orders', function (Request $request) {
        $vendor = $request->user()->vendor;
        $status = $request->query('status');

        $query = $vendor->orderItems()->with('order.user');

        if ($status) {
            $query->whereHas('order', fn($q) => $q->where('status', $status));
        }

        $orders = $query->latest()->get()->map(fn($item) => [
            'id' => $item->order->id,
            'order_number' => $item->order->order_number,
            'customer_name' => $item->order->user->name,
            'date' => $item->created_at->format('M d, Y'),
            'total' => $item->subtotal,
            'items' => $item->quantity,
            'status' => $item->order->status,
        ]);

        return response()->json($orders);
    });

    // Single order details
    Route::get('/orders/{id}', function ($id) {
        $vendor = auth()->user()->vendor;
        $order = $vendor->orderItems()->where('order_id', $id)
            ->with('order.user', 'product')
            ->firstOrFail();

        return response()->json([
            'order_number' => $order->order->order_number,
            'customer_name' => $order->order->user->name,
            'customer_email' => $order->order->user->email,
            'customer_phone' => $order->order->user->phone,
            'shipping_address' => $order->order->shipping_address,
            'date' => $order->created_at->format('M d, Y'),
            'total' => $order->order->total_amount,
            'items_count' => $vendor->orderItems()->where('order_id', $id)->count(),
            'status' => $order->order->status,
            'payment_method' => $order->order->payment_method,
            'payment_status' => $order->order->payment_status,
            'delivery_status' => $order->order->delivery_status,
            'items' => $vendor->orderItems()->where('order_id', $id)->get()->map(fn($item) => [
                'name' => $item->product->name,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'image' => $item->product->images->first()?->image_path,
            ]),
        ]);
    });

    // Update order status
    Route::patch('/orders/{id}/status', function (Request $request, $id) {
        $request->validate(['status' => 'required|in:pending,shipped,completed,cancelled,returned']);
        $vendor = auth()->user()->vendor;
        $order = $vendor->orderItems()->where('order_id', $id)->firstOrFail()->order;
        $order->update(['status' => $request->status]);
        return response()->json(['message' => 'Status updated']);
    });
});

Route::middleware('auth:sanctum')->prefix('vendor')->group(function () {
    // List products
    Route::get('/products', function (Request $request) {
        $vendor = $request->user()->vendor;
        $status = $request->query('status');

        $query = $vendor->products();

        if ($status) {
            $query->where('status', $status);
        }

        $products = $query->with('images')->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => $p->price,
            'stock' => $p->stock_quantity,
            'image' => $p->images->first()?->image_path,
            'status' => $p->status,
            'sales' => $p->orderItems()->count(),
        ]);

        return response()->json($products);
    });

    // Create product
    Route::post('/products', function (Request $request) {
        $vendor = $request->user()->vendor;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:100',
            'status' => 'in:active,draft,low_stock',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = $vendor->products()->create($request->only([
            'name', 'description', 'price', 'compare_price',
            'stock_quantity', 'sku', 'status'
        ]));

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $product->addMedia($image)->toMediaCollection('images');
            }
        }

        return response()->json($product, 201);
    });

    // Single product details
    Route::get('/products/{id}', function ($id) {
        $vendor = auth()->user()->vendor;
        $product = $vendor->products()->with('images', 'orderItems.order')->findOrFail($id);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'stock_quantity' => $product->stock_quantity,
            'sku' => $product->sku,
            'status' => $product->status,
            'images' => $product->images->pluck('image_path'),
            'sales' => $product->orderItems->count(),
            'orders' => $product->orderItems->map(fn($item) => [
                'order_number' => $item->order->order_number,
                'customer' => $item->order->user->name,
                'quantity' => $item->quantity,
                'total' => $item->subtotal,
                'date' => $item->created_at->format('M d, Y'),
            ]),
            // Add reviews when ready
        ]);
    });
});

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

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

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

Route::middleware('auth:sanctum')->prefix('vendor')->group(function () {
    Route::get('/payments', function () {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return response()->json(['error' => 'Vendor profile not found'], 404);
        }

        // Mock stats - replace with real calculations
        $stats = [
            'available' => 125350,
            'pending' => 0,
            'totalEarned' => 350000,
            'monthlyPayout' => 125350,
        ];

        $payouts = [
            [
                'id' => '1',
                'amount' => 125350,
                'status' => 'completed',
                'date' => 'Feb 15, 2026',
                'method' => 'Bank Transfer',
                'reference' => 'PAY-20260215-001',
            ],
            // Add more mock or real payouts
        ];

        return response()->json([
            'stats' => $stats,
            'recentPayouts' => $payouts,
        ]);
    });

    Route::post('/payouts/request', function (Request $request) {
        $request->validate(['amount' => 'required|numeric|min:1000']);

        // Create payout request logic here (e.g. save to Payout model)
        // For now, just return success
        return response()->json(['message' => 'Payout request submitted']);
    });
});

// Cart routes
Route::prefix('cart')->group(function () {
    Route::get('/', [App\Http\Controllers\CartController::class, 'index']);
    Route::post('/', [App\Http\Controllers\CartController::class, 'store']);
    Route::put('/{id}', [App\Http\Controllers\CartController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\CartController::class, 'destroy']);
    Route::delete('/', [App\Http\Controllers\CartController::class, 'clear']);
});

// Wishlist routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{id}', [WishlistController::class, 'destroy']);
    Route::delete('/wishlist', [WishlistController::class, 'clear']);
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

use App\Models\Vendor;

// Single vendor by slug (supports @ prefix or plain slug)
Route::get('/stores/{slug}', function ($slug) {
    // Remove @ prefix if present
    $cleanSlug = ltrim($slug, '@');

    $vendor = Vendor::where('slug', $cleanSlug)
        ->where('is_active', true)
        ->where('verification_status', 'verified')
        ->firstOrFail();

    return response()->json([
        'id' => $vendor->id,
        'shop_name' => $vendor->shop_name,
        'slug' => $vendor->slug,
        'description' => $vendor->description,
        'logo' => $vendor->getLogoUrlAttribute(),
        'banner' => $vendor->getCoverPhotoUrlAttribute(),
        'rating' => 4.8,
        'products_count' => $vendor->products()->count(),
    ]);
});

// Products for a vendor
Route::get('/stores/{slug}/products', function ($slug) {
    $cleanSlug = ltrim($slug, '@');

    $vendor = Vendor::where('slug', $cleanSlug)->firstOrFail();

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

Route::post('/verify-paystack', [PaymentController::class, 'verifyPaystack']);

Route::post('/webhooks/paystack', [\App\Http\Controllers\Webhook\PaystackWebhookController::class, 'handle']);