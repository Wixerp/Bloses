<?php

namespace App\Services\Seller;

use App\Models\Seller;

class SellerService
{
    public function registrationStatus(int $userId): bool
    {
        return Seller::where('user_id', $userId)->where('status_id', 36)->exists();
    }
}
