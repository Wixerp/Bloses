<?php

namespace App\Services\Categories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoriesService
{
    public function getCategories()
    {
        return DB::table('categories')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'categories.image',
                DB::raw('COUNT(products.id) as products_count')
            )
            ->groupBy(
                'categories.id',
                'categories.name',
                'categories.image'
            )
            ->orderBy('categories.name')
            ->get();
    }

    public function createCategory(array $data)
    {
        return DB::transaction(function () use ($data) {

            $slug = $this->generateUniqueSlug(
                $data['slug'] ?? $data['name']
            );

            $imagePath = null;

            if (
                isset($data['image']) &&
                $data['image'] instanceof UploadedFile
            ) {
                $imagePath = $data['image']->store(
                    'categories',
                    'public'
                );
            }

            $id = DB::table('categories')->insertGetId([
                // 'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'image' => $imagePath,
                'icon' => $data['icon'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('categories')
                ->where('id', $id)
                ->first();
        });
    }

    public function getCategoryById(int $id)
    {
        return DB::table('categories')
            ->where('id', $id)
            ->first();
    }

    public function updateCategory(
        int $id,
        array $data
    ) {
        return DB::transaction(function () use ($id, $data) {

            $category = DB::table('categories')
                ->where('id', $id)
                ->first();

            if (!$category) {
                abort(404, 'Category not found.');
            }


            /*
             * Generate new slug only if
             * name or slug is provided.
             */
            $slug = $category->slug;

            if (
                isset($data['name']) ||
                isset($data['slug'])
            ) {
                $slug = $this->generateUniqueSlug(
                    $data['slug']
                        ?? $data['name']
                        ?? $category->name,
                    $id
                );
            }


            /*
             * Keep old image by default.
             */
            $imagePath = $category->image;

            /*
             * New image uploaded
             */
            if (
                isset($data['image']) &&
                $data['image'] instanceof UploadedFile
            ) {

                /*
                 * Store new image
                 */
                $newImagePath = $data['image']->store(
                    'categories',
                    'public'
                );

                /*
                 * Delete old image
                 */
                if ($category->image) {
                    Storage::disk('public')->delete(
                        $category->image
                    );
                }

                $imagePath = $newImagePath;
            }


            DB::table('categories')
                ->where('id', $id)
                ->update([
                    // 'parent_id' => array_key_exists(
                    //     'parent_id',
                    //     $data
                    // )
                    //     ? $data['parent_id']
                    //     : $category->parent_id,

                    'name' => $data['name']
                        ?? $category->name,

                    'slug' => $slug,

                    'description' => array_key_exists(
                        'description',
                        $data
                    )
                        ? $data['description']
                        : $category->description,

                    'image' => $imagePath,

                    'icon' => array_key_exists(
                        'icon',
                        $data
                    )
                        ? $data['icon']
                        : $category->icon,

                    'sort_order' => $data['sort_order']
                        ?? $category->sort_order,

                    'is_active' => array_key_exists(
                        'is_active',
                        $data
                    )
                        ? $data['is_active']
                        : $category->is_active,

                    'is_featured' => array_key_exists(
                        'is_featured',
                        $data
                    )
                        ? $data['is_featured']
                        : $category->is_featured,

                    'updated_at' => now(),
                ]);


            return DB::table('categories')
                ->where('id', $id)
                ->first();
        });
    }

    public function deleteCategory(int $id): void
    {
        DB::transaction(function () use ($id) {

            $category = DB::table('categories')
                ->where('id', $id)
                ->first();

            if (!$category) {
                abort(404, 'Category not found.');
            }


            /*
             * Check child categories
             */
            // $hasChildren = DB::table('categories')
            //     ->where('parent_id', $id)
            //     ->exists();

            if ($hasChildren) {
                abort(
                    422,
                    'Category cannot be deleted because it has child categories.'
                );
            }


            /*
             * Check products
             */
            $hasProducts = DB::table('products')
                ->where('category_id', $id)
                ->exists();

            if ($hasProducts) {
                abort(
                    422,
                    'Category cannot be deleted because products are assigned to it.'
                );
            }


            /*
             * Delete image from storage
             */
            if ($category->image) {
                Storage::disk('public')->delete(
                    $category->image
                );
            }


            /*
             * Delete category
             */
            DB::table('categories')
                ->where('id', $id)
                ->delete();
        });
    }

    private function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {

        $slug = Str::slug($name);

        $originalSlug = $slug;

        $counter = 1;

        while (
            DB::table('categories')
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;

            $counter++;
        }

        return $slug;
    }

    public function getCategoryProducts(int $categoryId)
    {
        return DB::table('products')
            ->leftJoin('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('product_images',
                function ($join) {
                    $join->on('product_images.product_id','=','products.id')->where('product_images.is_primary', true);
                }
            )->where('products.category_id', $categoryId)->where('products.is_active', true)->where('product_variants.is_active', true)
            ->select('products.id', 'products.name', 'products.slug', 'product_variants.price',
                'product_variants.compare_at_price as discount_price', 'product_images.image_path'
            )->distinct('products.id')->orderByDesc('products.id')->paginate(20);
    }

}
