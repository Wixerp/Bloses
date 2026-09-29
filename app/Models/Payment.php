<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method_id',
        'amount',
        'transaction_id',
        'status_id',
        'paid_at',
        'failure_reason',
    ];

    protected $hidden = [
        'transaction_id',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(
            MasterType::class,
            'payment_method_id'
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
