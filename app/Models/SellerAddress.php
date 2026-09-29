<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'state_id',
        'city_id',
        'address_line_1',
        'address_line_2',
        'area',
        'postal_code',
        'country',
        'contact_person',
        'phone',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
