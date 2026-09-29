<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'shipping_method_id',
        'seller_id',
        'rate_type_id',
        'min_value',
        'max_value',
        'rate',
        'status_id',
    ];

    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'rate' => 'decimal:2',
        ];
    }

    public function shippingZone()
    {
        return $this->belongsTo(
            ShippingZone::class,
            'shipping_zone_id'
        );
    }

    public function shippingMethod()
    {
        return $this->belongsTo(
            ShippingMethod::class,
            'shipping_method_id'
        );
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function rateType()
    {
        return $this->belongsTo(
            MasterType::class,
            'rate_type_id'
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
