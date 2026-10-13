<?php

namespace App\Services\Categories;

use Illuminate\Support\Facades\DB;

class BuyerCategoryService
{
    public function getMenuDepartments(): array
    {
        $categories = DB::table('categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $children = DB::table('categories')
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        return $categories->map(function ($category) use ($children) {
            return [
                'heading' => $category->name,
                'items'   => ($children->get($category->id) ?? collect())->pluck('name')->values()->all(),
            ];
        })->values()->all();
    }

    public function getMenuCategories(): array
    {
        return DB::table('categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id'   => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ];
            })->values()->all();
    }

    /**
     * Parent + uske saare sub-categories ke IDs return karta hai
     */
    public function getCategoryIdsWithChildren(int $categoryId): array
    {
        $ids = DB::table('categories')
            ->where('id', $categoryId)
            ->orWhere('parent_id', $categoryId)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        return $ids;
    }

    /**
     * Category (parent ya sub) ke products laata hai
     * Parent select karne pe uske sub ke products bhi aate hain
     */
    public function getProductsByCategory(int $categoryId, int $perPage = 20)
    {
        $categoryIds = $this->getCategoryIdsWithChildren($categoryId);

        return DB::table('products')
            ->leftJoin('seller_store_settings', 'seller_store_settings.id', '=', 'products.store_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('master_types as status', 'status.id', '=', 'products.status_id')
            ->select([
                'products.id',
                'products.product_code',
                'products.name',
                'products.slug',
                'products.category_id',
                'categories.name as category_name',
                'products.is_deal',
                'products.deal_start_at',
                'products.deal_end_at',
                DB::raw("CASE WHEN products.brand IS NOT NULL AND products.brand != '' THEN products.brand ELSE 'No Brand' END AS brand"),
                'products.store_id',
                'seller_store_settings.store_name',
                'products.price',
                'products.list_price',
                'products.discount',
                'products.tax',
                'products.is_featured',
                'products.created_at',
                'products.is_active',
                'products.published_at',
                DB::raw("(SELECT CONCAT('" . config('app.url') . "/storage/', pi.image_path)
                          FROM product_images pi
                          WHERE pi.product_id = products.id
                          ORDER BY pi.is_primary DESC, pi.sort_order ASC
                          LIMIT 1) AS image"),
                DB::raw("COALESCE((SELECT ROUND(AVG(r.rating)::numeric, 1) FROM reviews r WHERE r.product_id = products.id), 0) AS rating"),
                DB::raw("(SELECT COUNT(*) FROM reviews r WHERE r.product_id = products.id) AS reviews_count"),
                DB::raw("CASE WHEN COALESCE(CAST(NULLIF(products.qty, '') AS INTEGER), 0) > 0 THEN true ELSE false END AS \"inStock\""),
            ])
            ->whereIn('products.category_id', $categoryIds)
            ->where('products.is_active', true)
            ->whereNotNull('products.published_at')
            ->where('status.description', 'Active')
            ->orderByDesc('products.id')
            ->paginate($perPage);
    }

    /**
     * Category details + children (agar parent hai)
     */
    public function getCategoryWithChildren(int $categoryId)
    {
        $category = DB::table('categories')
            ->where('id', $categoryId)
            ->where('is_active', true)
            ->first();

        if (!$category) {
            abort(404, 'Category not found.');
        }

        $children = DB::table('categories')
            ->where('parent_id', $categoryId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'category' => $category,
            'children' => $children,
        ];
    }
}
