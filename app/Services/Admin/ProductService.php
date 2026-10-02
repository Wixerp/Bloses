<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class ProductService
{
    public function getSellers()
    {
        return DB::table('sellers')
            ->leftJoin('seller_store_settings','seller_store_settings.seller_id','=','sellers.id')
            ->leftJoin('products','products.store_id','=','seller_store_settings.id')
            ->select('sellers.id','sellers.business_name','sellers.slug','sellers.status_id',
                DB::raw('COUNT(DISTINCT seller_store_settings.id) as stores_count'),
                DB::raw('COUNT(DISTINCT products.id) as products_count')
            )
            ->groupBy('sellers.id','sellers.business_name','sellers.slug','sellers.status_id')
            ->orderBy('sellers.business_name')->get();
    }

    public function getSellerStores(int $sellerId)
    {
        return DB::table('seller_store_settings')
            ->leftJoin('products','products.store_id','=','seller_store_settings.id')
            ->where('seller_store_settings.seller_id', $sellerId)
            ->select('seller_store_settings.id','seller_store_settings.seller_id','seller_store_settings.store_name',
                'seller_store_settings.store_slug','seller_store_settings.logo','seller_store_settings.is_active',
                'seller_store_settings.is_verified',DB::raw('COUNT(DISTINCT products.id) as products_count')
            )
            ->groupBy('seller_store_settings.id','seller_store_settings.seller_id','seller_store_settings.store_name',
                'seller_store_settings.store_slug','seller_store_settings.logo','seller_store_settings.is_active',
                'seller_store_settings.is_verified'
            )->orderBy('seller_store_settings.store_name')->get();
    }

    public function getStoreProducts(int $storeId, array $filters = [])
    {
        $query = DB::table('products')
            ->leftJoin('seller_store_settings', 'seller_store_settings.id', '=', 'products.store_id'
            )
            ->leftJoin('sellers', 'sellers.id', '=', 'seller_store_settings.seller_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_variants',
                function ($join) {
                    $join->on('product_variants.product_id', '=', 'products.id')
                    ->where('product_variants.is_default', true);
                }
            )->where('products.store_id', $storeId)
            ->select('products.id','products.store_id','products.category_id','products.brand_id','products.name',
                'products.slug','products.stock_qty','products.qty','products.short_description','products.product_code',
                'products.model_number','products.manufacturer','products.status_id','products.is_featured','products.is_active',
                'products.sort_order','products.published_at','products.created_at',
                'seller_store_settings.store_name','sellers.id as seller_id','sellers.business_name as seller_name',
                'categories.name as category_name',
                'product_variants.price','product_variants.stock_qty','product_variants.sku'
            );

        $this->applyProductFilters($query, $filters);

        return $query->orderByDesc('products.created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function getAllProducts(array $filters = [])
    {
        $query = DB::table('products')
            ->leftJoin('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->leftJoin('sellers','sellers.id','=','seller_store_settings.seller_id')
            ->leftJoin('categories','categories.id','=','products.category_id')
            ->leftJoin('product_variants',
                function ($join) {
                    $join->on('product_variants.product_id','=','products.id')
                    ->where('product_variants.is_default', true);
                }
            )
            ->select('products.id','products.store_id','products.category_id','products.brand_id',
                'products.name','products.slug','products.stock_qty','products.qty','products.short_description',
                'products.product_code','products.model_number','products.manufacturer','products.status_id',
                'products.is_featured','products.is_active','products.sort_order','products.published_at','products.created_at',
                'seller_store_settings.store_name','sellers.id as seller_id','sellers.business_name as seller_name',
                'categories.name as category_name',
                'product_variants.price','product_variants.stock_qty','product_variants.sku'
            );

        $this->applyProductFilters($query, $filters);

        return $query->orderByDesc('products.created_at')->paginate($filters['per_page'] ?? 20);
    }

    private function applyProductFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'ILIKE', "%{$search}%")
                    ->orWhere('products.product_code', 'ILIKE', "%{$search}%")
                    ->orWhere('products.model_number', 'ILIKE', "%{$search}%")
                    ->orWhere('product_variants.sku', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($filters['seller_id'])) {
            $query->where('sellers.id', $filters['seller_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('products.store_id', $filters['store_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (!empty($filters['status_id'])) {
            $query->where('products.status_id', $filters['status_id']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('products.is_featured', (bool) $filters['is_featured']);
        }

        if (isset($filters['is_active'])) {
            $query->where('products.is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['stock'])) {
            if ($filters['stock'] === 'out_of_stock') {
                $query->where('product_variants.stock_qty', '<=', 0);
            }
            elseif ($filters['stock'] === 'in_stock') {
                $query->where('product_variants.stock_qty', '>', 0);

            }
            elseif ($filters['stock'] === 'low_stock') {
                $query->whereBetween('product_variants.stock_qty', [1, 10]);
            }
        }

        if (!empty($filters['brand_id'])) {
            $query->where('products.brand_id', $filters['brand_id']);
        }

        if (isset($filters['min_price'])) {
            $query->where('product_variants.price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('product_variants.price', '<=', $filters['max_price']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('products.created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('products.created_at', '<=', $filters['date_to']);
        }

        $allowedSorts = [
            'name' => 'products.name',
            'price' => 'product_variants.price',
            'stock' => 'product_variants.stock_qty',
            'created_at' => 'products.created_at',
        ];

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        if (isset($allowedSorts[$sortBy])) {
            $query->orderBy($allowedSorts[$sortBy], in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc');
        }
    }

}
