<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Vendor extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'shop_name',
        'slug',
        'description',
        'logo',
        'banner',
        'business_type',
        'business_category',
        'store_url',
        'business_address',
        'tax_id',
        'verification_status',
        'commission_rate',
        'is_active',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(VendorPayout::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_photo')
             ->singleFile()
             ->useDisk('public');

        $this->addMediaCollection('logo')
             ->singleFile()
             ->useDisk('public');
    }

    // Optional: define how to get URLs
    public function getCoverPhotoUrlAttribute()
    {
        return $this->getFirstMediaUrl('cover_photo') ?: null;
    }

    public function getLogoUrlAttribute()
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }
}