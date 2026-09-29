<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'slug',
        'business_email',
        'business_phone',
        'description',
        'status_id',
        'approved_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }

    public function addresses()
    {
        return $this->hasMany(SellerAddress::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(SellerBankAccount::class);
    }

    public function documents()
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function commissions()
    {
        return $this->hasMany(SellerCommission::class);
    }

    public function payouts()
    {
        return $this->hasMany(SellerPayout::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function shippingSettings()
    {
        return $this->hasOne(SellerShippingSetting::class);
    }

    public function storeSettings()
    {
        return $this->hasOne(SellerStoreSetting::class);
    }

    public function status()
    {
        return $this->belongsTo(MasterType::class, 'status_id');
    }
}
