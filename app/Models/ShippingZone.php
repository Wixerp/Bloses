<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'status_id',
    ];

    public function status()
    {
        return $this->belongsTo(
            MasterType::class,
            'status_id'
        );
    }

    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
