<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'account_type_id',
        'account_holder_name',
        'bank_name',
        'account_number',
        'iban',
        'branch_name',
        'branch_code',
        'mobile_number',
        'is_primary',
        'is_verified',
        'verified_at',
    ];

    protected $hidden = [
        'account_number',
        'iban',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function accountType()
    {
        return $this->belongsTo(
            MasterType::class,
            'account_type_id'
        );
    }
}
