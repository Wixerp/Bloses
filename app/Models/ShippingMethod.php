<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type_id',
        'status_id',
    ];

    public function type()
    {
        return $this->belongsTo(
            MasterType::class,
            'type_id'
        );
    }

    public function status()
    {
        return $this->belongsTo(
            MasterType::class,
            'status_id'
        );
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function shippingRates()
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function sellerShippingSettings()
    {
        return $this->hasMany(
            SellerShippingSetting::class,
            'shipping_method_id'
        );
    }
}
