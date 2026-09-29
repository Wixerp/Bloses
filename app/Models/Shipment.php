<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'seller_id',
        'shipping_method_id',
        'shipping_zone_id',
        'tracking_number',
        'status_id',
        'shipped_at',
        'delivered_at',
        'estimated_delivery_at',
    ];

    protected $hidden = [
        'tracking_number',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
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

    public function shippingZone()
    {
        return $this->belongsTo(
            ShippingZone::class,
            'shipping_zone_id'
        );
    }

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function status()
    {
        return $this->belongsTo(
            MasterType::class,
            'status_id'
        );
    }
}
