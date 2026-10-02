<?php

namespace App\Services\Buyer;

use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function getWishlist()
    {
        if (!auth()->check()) {
            abort(401, 'Please login to view your wishlist.');
        }

        $userId = auth()->id();

        return DB::table('wishlists')
            ->join('products','products.id','=','wishlists.product_id')
            ->leftJoin('product_variants','product_variants.id','=','wishlists.product_variant_id')
            ->where('wishlists.user_id', $userId)
            ->select(['wishlists.id as wishlist_id',
                'products.id as product_id','products.name as product_name','products.slug','products.product_code',
                'products.price as product_price','products.discount_percentage','products.is_active',
                'product_variants.id as variant_id','product_variants.name as variant_name','product_variants.sku as variant_sku','product_variants.price as variant_price',
                'wishlists.created_at as wished_at',
            ])->orderByDesc('wishlists.id')->get();
    }

    public function wishlist(array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to manage your wishlist.');
        }

        $userId = auth()->id();

        return DB::transaction(function () use ($data, $userId) {
            $productId = $data['product_id'];
            $variantId = $data['product_variant_id'] ?? null;

            if ($data['case'] === 'add') {
                $wishlist = DB::table('wishlists')->where('user_id', $userId)->where('product_id', $productId)
                    ->where(function ($query) use ($variantId) {
                        if ($variantId === null) {
                            $query->whereNull('product_variant_id');
                        } else {
                            $query->where('product_variant_id', $variantId);
                        }
                    })->first();

                if (!$wishlist) {
                    $wishlistId = DB::table('wishlists')->insertGetId([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $wishlist = DB::table('wishlists')->where('id', $wishlistId)->first();
                }

                return $wishlist;
            }

            $wishlist = DB::table('wishlists')->where('user_id', $userId)->where('product_id', $productId)
                ->where(function ($query) use ($variantId) {
                    if ($variantId === null) {
                        $query->whereNull('product_variant_id');
                    } else {
                        $query->where('product_variant_id', $variantId);
                    }

                })->first();

            if (!$wishlist) {
                abort(404, 'Product is not in your wishlist.');
            }

            DB::table('wishlists')->where('id', $wishlist->id)->delete();

            return [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'removed' => true,
            ];
        });
    }

}
