<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerShippingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'shipping_method_id',
        'is_enabled',
        'settings',
        'status_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(
            ShippingMethod::class,
            'shipping_method_id'
        );
    }

    public function status()
    {
        return $this->belongsTo(
            MasterType::class,
            'status_id'
        );
    }
}
