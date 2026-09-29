<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerStoreSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'store_name',
        'store_slug',
        'store_description',
        'logo',
        'banner',
        'email',
        'phone',
        'website',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'youtube_url',
        'is_active',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
