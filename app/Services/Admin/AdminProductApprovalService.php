<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductApprovalService
{
    private function statusId(string $value): int
    {
        $id = DB::table('master_types')->where('key', 'product_status')->where('value', $value)->value('id');

        if (!$id) {
            throw ValidationException::withMessages([
                'status' => [
                    "Product status '{$value}' is missing from master_types.",
                ],
            ]);
        }

        return (int) $id;
    }

    private function productQuery()
    {
        return DB::table('products as p')
            ->leftJoin('seller_store_settings as store','store.id','=','p.store_id')
            ->leftJoin('sellers as s', 's.id', '=', 'store.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('master_types as st', function ($join) {
                $join->on('st.id', '=', 'p.status_id')->where('st.key', '=', 'product_status');
            })->select([
                'p.id','p.store_id','p.category_id','p.brand_id','p.name','p.slug','p.product_code',
                'p.model_number','p.short_description','p.description','p.price','p.list_price',
                'p.discount','p.discount_percentage','p.qty','p.stock_qty','p.stock_quantity',
                'p.status_id','st.value as status','st.description as status_description','p.admin_note',
                'p.reviewed_by','p.reviewed_at','p.is_active','p.is_featured','p.published_at',
                'p.created_at','p.updated_at','store.store_name','store.seller_id','u.name as seller_name',
                'u.email as seller_email',
            ])->addSelect([
                'primary_image' => DB::table('product_images as pi')->select('pi.image_path')
                    ->whereColumn('pi.product_id', 'p.id')->where('pi.is_primary', true)
                    ->orderBy('pi.sort_order')->limit(1),
            ]);
    }


    public function index(array $filters = []): array
    {
        $query = $this->productQuery();

        if (!empty($filters['status'])) {
            $statusId = $this->statusId($filters['status']);
            $query->where('p.status_id', $statusId);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'ILIKE', "%{$search}%")
                    ->orWhere('p.product_code', 'ILIKE', "%{$search}%")
                    ->orWhere('store.store_name', 'ILIKE', "%{$search}%")
                    ->orWhere('u.name', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($filters['seller_id'])) {
            $query->where('store.seller_id', $filters['seller_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('p.created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('p.created_at', '<=', $filters['date_to']);
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $products = $query
            ->orderByDesc('p.created_at')
            ->paginate($perPage);

        return [
            'items' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ];
    }


    public function show(int $productId): array
    {
        $product = $this->productQuery()->where('p.id', $productId)->first();

        if (!$product) {
            abort(404, 'Product not found.');
        }

        $product->images = DB::table('product_images')
            ->where('product_id', $productId)->orderByDesc('is_primary')->orderBy('sort_order')
            ->get([
                'id','product_id','product_variant_id','image_path',
                'alt_text','sort_order','is_primary',
            ]);

        return ['product' => $product];
    }

    public function approve(int $productId, int $adminId): array
    {
        return DB::transaction(function () use ($productId, $adminId) {
            $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();

            if (!$product) {
                abort(404, 'Product not found.');
            }

            if ((int) $product->status_id !== $this->statusId('pending_approval')) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending products can be approved.'],
                ]);
            }

            DB::table('products')->where('id', $productId)
                ->update([
                    'status_id' => $this->statusId('active'),
                    'is_active' => true,
                    'published_at' => now(),
                    'admin_note' => null,
                    'reviewed_by' => $adminId,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            return [
                'product_id' => $productId,
                'status' => 'active',
                'message' => 'Product approved successfully.',
            ];
        });
    }

    public function reject(int $productId, int $adminId, string $adminNote): array {
        return DB::transaction(function () use ($productId, $adminId, $adminNote) {
            $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();

            if (!$product) {
                abort(404, 'Product not found.');
            }

            if ((int) $product->status_id !== $this->statusId('pending_approval')) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending products can be rejected.'],
                ]);
            }

            DB::table('products')->where('id', $productId)
                ->update([
                    'status_id' => $this->statusId('rejected'),
                    'is_active' => false,
                    'published_at' => null,
                    'admin_note' => $adminNote,
                    'reviewed_by' => $adminId,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            return [
                'product_id' => $productId,
                'status' => 'rejected',
                'admin_note' => $adminNote,
                'message' => 'Product rejected successfully.',
            ];
        });
    }

}
