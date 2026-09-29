<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'description',
        'logo',
        'status_id',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function settings()
    {
        return $this->hasOne(SellerStoreSetting::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function status()
    {
        return $this->belongsTo(MasterType::class, 'status_id');
    }
}
