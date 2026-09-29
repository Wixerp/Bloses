<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'bank_account_id',
        'amount',
        'payout_method_id',
        'status_id',
        'transaction_reference',
        'processed_at',
        'note',
    ];

    protected $hidden = [
        'transaction_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(
            SellerBankAccount::class,
            'bank_account_id'
        );
    }

    public function payoutMethod()
    {
        return $this->belongsTo(
            MasterType::class,
            'payout_method_id'
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
