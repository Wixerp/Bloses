<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function getReviews(array $filters = [])
    {
        $query = DB::table('reviews')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'reviews.user_id')
            ->leftJoin('products', 'products.id', '=', 'reviews.product_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'reviews.seller_id')
            ->select(['reviews.id','reviews.user_id','reviews.product_id','reviews.product_variant_id',
                'reviews.order_item_id','reviews.seller_id','buyer.name as buyer_name','products.name as product_name',
                'seller.name as seller_name','reviews.rating','reviews.title','reviews.review as comment',
                'reviews.is_verified_purchase','reviews.is_approved','reviews.created_at',
            ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('buyer.name', 'ilike', "%{$search}%")
                    ->orWhere('products.name', 'ilike', "%{$search}%")
                    ->orWhere('seller.name', 'ilike', "%{$search}%")
                    ->orWhere('reviews.title', 'ilike', "%{$search}%")
                    ->orWhere('reviews.review', 'ilike', "%{$search}%");
            });
        }

        if (array_key_exists('is_approved', $filters)) {
            $status = $filters['is_approved'];

            if ($status === 'null') {
                $query->whereNull('reviews.is_approved');
            } elseif ($status === 'true' || $status === 'false') {
                $query->where('reviews.is_approved', $status === 'true');
            }
        }

        return $query->orderByDesc('reviews.created_at')->get();
    }

    public function getReviewById(int $id)
    {
        return DB::table('reviews')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'reviews.user_id')
            ->leftJoin('products', 'products.id', '=', 'reviews.product_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'reviews.seller_id')
            ->where('reviews.id', $id)
            ->select(['reviews.id','reviews.user_id','reviews.product_id','reviews.product_variant_id','reviews.order_item_id',
                'reviews.seller_id','buyer.name as buyer_name','products.name as product_name','seller.name as seller_name',
                'reviews.title as subject','reviews.rating','reviews.review as description','reviews.review_answer',
                'reviews.is_verified_purchase','reviews.is_approved','reviews.approved_at','reviews.created_at','reviews.updated_at',
            ])->first();
    }
}
