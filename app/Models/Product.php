<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'store_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'sale_price',
        'status_id',
        'brand',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function SellerStoreSetting()
    {
        return $this->belongsTo(SellerStoreSetting::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function questions()
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function status()
    {
        return $this->belongsTo(MasterType::class, 'status_id');
    }
}
