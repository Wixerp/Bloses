<?php

namespace App\Services\Categories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Log};
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

        // ===== DEBUG START =====
        Log::info('SERVICE: UPDATE START', [
            'id' => $id,
            'old_image' => $category->image,
            'has_image_key' => isset($data['image']),
            'image_class' => isset($data['image']) ? get_class($data['image']) : null,
            'is_uploaded_file' => isset($data['image']) && $data['image'] instanceof UploadedFile,
            'image_original_name' => isset($data['image']) && $data['image'] instanceof UploadedFile
                ? $data['image']->getClientOriginalName() : null,
            'image_size' => isset($data['image']) && $data['image'] instanceof UploadedFile
                ? $data['image']->getSize() : null,
            'image_valid' => isset($data['image']) && $data['image'] instanceof UploadedFile
                ? $data['image']->isValid() : null,
        ]);
        // ===== DEBUG END =====

        $imagePath = $category->image;

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $newImagePath = $data['image']->store('categories', 'public');

            // ===== DEBUG START =====
            Log::info('SERVICE: IMAGE STORED', [
                'new_path' => $newImagePath,
                'path_type' => gettype($newImagePath),
                'file_exists' => $newImagePath
                    ? Storage::disk('public')->exists($newImagePath)
                    : false,
                'full_url' => $newImagePath
                    ? Storage::disk('public')->url($newImagePath)
                    : null,
            ]);
            // ===== DEBUG END =====

            if ($category->image) {
                $deleted = Storage::disk('public')->delete($category->image);
                Log::info('SERVICE: OLD IMAGE DELETE', [
                    'old_path' => $category->image,
                    'deleted' => $deleted,
                ]);
            }

            $imagePath = $newImagePath;
        }

        // ... update call ...

        $updated = DB::table('categories')->where('id', $id)->first();

        // ===== DEBUG START =====
        Log::info('SERVICE: AFTER UPDATE', [
            'db_image_value' => $updated->image,
            'expected_image' => $imagePath,
            'match' => $updated->image === $imagePath,
        ]);
        // ===== DEBUG END =====

        return $updated;
    });
}

    public function deleteCategory(int $id): void
    {
        DB::transaction(function () use ($id) {

            $category = DB::table('categories')->where('id', $id)->first();

            if (!$category) {
                abort(404, 'Category not found.');
            }

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


}
