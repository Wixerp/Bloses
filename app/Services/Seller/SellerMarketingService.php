<?php

namespace App\Services\Seller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerMarketingService
{
    private function sellerId(): int
    {
        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        if (!$sellerId) {
            abort(403, 'Seller profile not found.');
        }

        return (int) $sellerId;
    }

    private function ownedProduct(int $productId)
    {
        return DB::table('products as p')
            ->join(
                'seller_store_settings as s',
                's.id',
                '=',
                'p.store_id'
            )
            ->where('s.seller_id', $this->sellerId())
            ->where('p.id', $productId)
            ->select('p.*')
            ->first();
    }

    public function products(Request $request): array
    {
        $sellerId = $this->sellerId();

        $query = DB::table('products as p')
            ->join(
                'seller_store_settings as s',
                's.id',
                '=',
                'p.store_id'
            )
            ->where('s.seller_id', $sellerId)
            ->select(
                'p.id',
                'p.store_id',
                'p.name',
                'p.product_code',
                'p.price',
                'p.list_price',
                'p.discount',
                'p.discount_percentage',
                'p.is_flash_sale',
                'p.is_deal',
                'p.deal_start_at',
                'p.deal_end_at',
                'p.is_active',
                'p.status_id'
            );

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'ILIKE', "%{$search}%")
                  ->orWhere('p.product_code', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('is_deal')) {
            $query->where(
                'p.is_deal',
                $request->boolean('is_deal')
            );
        }

        return $query
            ->orderByDesc('p.id')
            ->paginate($request->integer('per_page', 15))
            ->toArray();
    }

    public function updatePromotion(
        int $productId,
        array $data
    ): object {
        $product = $this->ownedProduct($productId);

        if (!$product) {
            abort(404, 'Product not found in your store.');
        }

        $updates = [];

        if (array_key_exists('discount', $data)) {
            $updates['discount'] = (string) $data['discount'];
        }

        if (array_key_exists('discount_percentage', $data)) {
            $updates['discount_percentage'] =
                (string) $data['discount_percentage'];
        }

        if (array_key_exists('is_deal', $data)) {
            $updates['is_deal'] = $data['is_deal'];
        }

        if (array_key_exists('is_flash_sale', $data)) {
            // This column is VARCHAR in the existing database.
            $updates['is_flash_sale'] =
                $data['is_flash_sale'] ? 'true' : 'false';
        }

        if (array_key_exists('deal_start_at', $data)) {
            $updates['deal_start_at'] = $data['deal_start_at']
                ? date('Y-m-d H:i:s', strtotime($data['deal_start_at']))
                : null;
        }

        if (array_key_exists('deal_end_at', $data)) {
            $updates['deal_end_at'] = $data['deal_end_at']
                ? date('Y-m-d H:i:s', strtotime($data['deal_end_at']))
                : null;
        }

        if (!$updates) {
            throw ValidationException::withMessages([
                'promotion' => ['At least one promotion field is required.'],
            ]);
        }

        DB::table('products')
            ->where('id', $productId)
            ->update(array_merge($updates, [
                'updated_at' => now(),
            ]));

        return $this->ownedProduct($productId);
    }

    public function couponUsageReport(Request $request): array
    {
        $sellerId = $this->sellerId();

        $query = DB::table('coupon_usages as cu')
            ->join('coupons as c', 'c.id', '=', 'cu.coupon_id')
            ->join('orders as o', 'o.id', '=', 'cu.order_id')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('oi.seller_id', $sellerId)
            ->select(
                'c.id as coupon_id',
                'c.code',
                'c.name',
                DB::raw('COUNT(DISTINCT cu.id) as usage_count'),
                DB::raw('SUM(cu.discount_amount) as recorded_discount_amount'),
                DB::raw('MAX(cu.used_at) as last_used_at')
            );

        if ($request->filled('from_date')) {
            $query->whereDate('cu.used_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('cu.used_at', '<=', $request->input('to_date'));
        }

        return $query
            ->groupBy('c.id', 'c.code', 'c.name')
            ->orderByDesc(DB::raw('MAX(cu.used_at)'))
            ->paginate($request->integer('per_page', 15))
            ->toArray();
    }
}
