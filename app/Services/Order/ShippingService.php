<?php

namespace App\Services\Order;

use Illuminate\Support\Facades\DB;

class ShippingService
{
    public function shippingMethods()
    {
        return DB::table('shipping_methods')
            ->where('is_active', true)
            ->select([
                'id','name','code','description','base_price',
                'price_per_kg','estimated_min_days','estimated_max_days',
            ])->orderBy('name')->get();
    }

    private function getSellerId(): int
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        return (int) $sellerId;
    }
}




// /seller/shipping-methods

// {
//     "success": true,
//     "data": [
//         {
//             "id": 1,
//             "name": "TCS",
//             "code": "tcs",
//             "description": "TCS",
//             "base_price": "200.00",
//             "price_per_kg": "50.00",
//             "estimated_min_days": 2,
//             "estimated_max_days": 5
//         },
//         {
//             "id": 2,
//             "name": "Leopards",
//             "code": "leopards",
//             "description": "Leopards",
//             "base_price": "180.00",
//             "price_per_kg": "45.00",
//             "estimated_min_days": 2,
//             "estimated_max_days": 5
//         }
//     ]
// }
