<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'return_id',
        'user_id',
        'amount',
        'refund_method_id',
        'transaction_id',
        'reason',
        'status_id',
        'processed_at',
    ];

    protected $hidden = [
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function return()
    {
        return $this->belongsTo(
            ReturnModel::class,
            'return_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refundMethod()
    {
        return $this->belongsTo(
            MasterType::class,
            'refund_method_id'
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
