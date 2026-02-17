<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'category_id', 'name', 'slug', 'description',
        'price', 'compare_price', 'stock_quantity', 'sku',
        'status', 'is_featured', 'views_count',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Add this one!
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    // Optional: if you have variations later
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    // Optional: reviews
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}