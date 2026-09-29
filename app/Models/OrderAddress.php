<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'address_type_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'country_id',
        'state_id',
        'city_id',
        'address_line_1',
        'address_line_2',
        'postal_code',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function addressType()
    {
        return $this->belongsTo(
            MasterType::class,
            'address_type_id'
        );
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
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
