<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'order_id',
        'order_item_id',
        'commission_type_id',
        'commission_rate',
        'commission_amount',
        'status_id',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function commissionType()
    {
        return $this->belongsTo(MasterType::class, 'commission_type_id');
    }

    public function status()
    {
        return $this->belongsTo(MasterType::class, 'status_id');
    }
}
