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
    $categories = DB::table('categories')
        ->leftJoin('products', 'products.category_id', '=', 'categories.id')
        ->leftJoin('categories as parent', 'parent.id', '=', 'categories.parent_id')
        ->select(
            'categories.id',
            'categories.name',
            'categories.image',
            'categories.parent_id',
            'parent.name as parent_name',
            DB::raw('COUNT(DISTINCT products.id) as products_count')
        )
        ->groupBy(
            'categories.id',
            'categories.name',
            'categories.image',
            'categories.parent_id',
            'parent.name'
        )
        ->orderBy('categories.name')
        ->get();

    $allCategories = $categories->keyBy('id');

    foreach ($categories as $category) {
        $category->children = $categories
            ->where('parent_id', $category->id)
            ->values();
    }

    return $categories;
}

    public function createCategory(array $data)
    {
        return DB::transaction(function () use ($data) {
            $parentId = $data['parent_id'] ?? null;

            // Validate parent category
            if ($parentId !== null) {
                $parentExists = DB::table('categories')
                    ->where('id', $parentId)
                    ->exists();

                if (!$parentExists) {
                    abort(422, 'Selected parent category does not exist.');
                }
            }
            $slug = $this->generateUniqueSlug(
                $data['slug'] ?? $data['name']
            );

            $imagePath = null;

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $imagePath = $data['image']->store('categories', 'public');
            }

            $id = DB::table('categories')->insertGetId([
                'parent_id' => $parentId,
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

            return DB::table('categories')->where('id', $id)->first();
        });
    }

    public function getCategoryById(int $id)
    {
        return DB::table('categories')->where('id', $id)->first();
    }

    public function updateCategory(int $id, array $data) {
        return DB::transaction(function () use ($id, $data) {

            $category = DB::table('categories')->where('id', $id)->first();

            if (!$category) {
                abort(404, 'Category not found.');
            }

            $slug = $category->slug;

            if (isset($data['name']) || isset($data['slug'])) {
                $slug = $this->generateUniqueSlug(
                    $data['slug'] ?? $data['name'] ?? $category->name,
                    $id
                );
            }

            $imagePath = $category->image;

            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $newImagePath = $data['image']->store('categories', 'public');

                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }

                $imagePath = $newImagePath;
            }


            DB::table('categories')->where('id', $id)
                ->update([
                    // 'parent_id' => array_key_exists(
                    //     'parent_id',
                    //     $data
                    // )
                    //     ? $data['parent_id']
                    //     : $category->parent_id,

                    'name' => $data['name'] ?? $category->name,
                    'slug' => $slug,
                    'description' => array_key_exists('description', $data) ? $data['description'] : $category->description,
                    'image' => $imagePath,
                    'icon' => array_key_exists('icon', $data) ? $data['icon'] : $category->icon,
                    'sort_order' => $data['sort_order'] ?? $category->sort_order,
                    'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $category->is_active,
                    'is_featured' => array_key_exists('is_featured', $data) ? $data['is_featured'] : $category->is_featured,
                    'updated_at' => now(),
                ]);


            return DB::table('categories')->where('id', $id)->first();
        });
    }


    public function deleteCategory(int $id): void
    {
        DB::transaction(function () use ($id) {

            $category = DB::table('categories')->where('id', $id)->first();

            if (!$category) {
                abort(404, 'Category not found.');
            }

            // $hasChildren = DB::table('categories')
            //     ->where('parent_id', $id)
            //     ->exists();

            // if ($hasChildren) {
            //     abort(
            //         422,
            //         'Category cannot be deleted because it has child categories.'
            //     );
            // }

            $hasProducts = DB::table('products')->where('category_id', $id)->exists();

            if ($hasProducts) {
                abort(422,'Category cannot be deleted because products are assigned to it.');
            }

            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            DB::table('categories')->where('id', $id)->delete();
        });
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string {

        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (
            DB::table('categories')->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where('id', '!=', $ignoreId)
                )->exists()
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

    // public function updateCategory(int $id, array $data)
    // {
    //     return DB::transaction(function () use ($id, $data) {
    //         $category = DB::table('categories')
    //             ->where('id', $id)
    //             ->first();

    //         if (!$category) {
    //             abort(404, 'Category not found.');
    //         }

    //         // Generate slug only if name or slug is provided
    //         $slug = $category->slug;

    //         if (isset($data['name']) || isset($data['slug'])) {
    //             $slug = $this->generateUniqueSlug(
    //                 $data['slug'] ?? $data['name'] ?? $category->name,
    //                 $id
    //             );
    //         }

    //         // Keep existing image unless a new image is uploaded
    //         $imagePath = $category->image;

    //         if (
    //             isset($data['image']) &&
    //             $data['image'] instanceof UploadedFile
    //         ) {
    //             $newImagePath = $data['image']->store(
    //                 'categories',
    //                 'public'
    //             );

    //             if ($category->image) {
    //                 Storage::disk('public')->delete($category->image);
    //             }

    //             $imagePath = $newImagePath;
    //         }

    //         // Parent category handling
    //         $parentId = array_key_exists('parent_id', $data)
    //             ? $data['parent_id']
    //             : $category->parent_id;

    //         // Prevent category from being its own parent
    //         if ($parentId !== null && (int) $parentId === $id) {
    //             abort(422, 'A category cannot be its own parent.');
    //         }

    //         // Parent must exist
    //         if ($parentId !== null) {
    //             $parentExists = DB::table('categories')
    //                 ->where('id', $parentId)
    //                 ->exists();

    //             if (!$parentExists) {
    //                 abort(422, 'Selected parent category does not exist.');
    //             }

    //             // Prevent circular hierarchy
    //             $currentParentId = (int) $parentId;

    //             while (true) {
    //                 if ($currentParentId === $id) {
    //                     abort(
    //                         422,
    //                         'A category cannot be moved under its own descendant.'
    //                     );
    //                 }

    //                 $parent = DB::table('categories')
    //                     ->where('id', $currentParentId)
    //                     ->first(['id', 'parent_id']);

    //                 if (!$parent || $parent->parent_id === null) {
    //                     break;
    //                 }

    //                 $currentParentId = (int) $parent->parent_id;
    //             }
    //         }

    //         DB::table('categories')
    //             ->where('id', $id)
    //             ->update([
    //                 'parent_id' => $parentId,
    //                 'name' => $data['name'] ?? $category->name,
    //                 'slug' => $slug,
    //                 'description' => array_key_exists('description', $data)
    //                     ? $data['description']
    //                     : $category->description,
    //                 'image' => $imagePath,
    //                 'icon' => array_key_exists('icon', $data)
    //                     ? $data['icon']
    //                     : $category->icon,
    //                 'sort_order' => $data['sort_order'] ?? $category->sort_order,
    //                 'is_active' => array_key_exists('is_active', $data)
    //                     ? $data['is_active']
    //                     : $category->is_active,
    //                 'is_featured' => array_key_exists('is_featured', $data)
    //                     ? $data['is_featured']
    //                     : $category->is_featured,
    //                 'updated_at' => now(),
    //             ]);

    //         return DB::table('categories')
    //             ->where('id', $id)
    //             ->first();
    //     });
    // }

}
