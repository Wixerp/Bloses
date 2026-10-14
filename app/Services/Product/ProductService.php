<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Auth, Storage, Log};
use Illuminate\Support\Str;

class ProductService
{
    public function getAll()
    {
        $sellerId = auth()->id();

        $products = DB::table('products')
            ->leftJoin('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->leftJoin('master_types as status','status.id','=','products.status_id')
            ->where('seller_store_settings.seller_id', $sellerId)
            ->select(
                'products.id','products.product_code','products.name',
                'seller_store_settings.store_name as store_name',
                'status.description as status','products.is_featured','products.is_active','products.published_at',
                DB::raw("CASE WHEN products.brand IS NOT NULL AND products.brand != '' THEN products.brand ELSE 'No Brand' END AS brand"),
                DB::raw("(SELECT CONCAT( '" . rtrim(config('app.url'), '/') . "/storage/', pi.image_path)
                        FROM product_images pi WHERE pi.product_id = products.id
                        ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1
                    ) AS image
                ")
            )->orderByDesc('products.id')->get();

        return $products;
    }

    public function findById(int $id): object
    {
        $product = DB::table('products')
            ->leftJoin('seller_store_settings', 'seller_store_settings.id', '=', 'products.store_id')
            ->leftJoin('master_types as status', 'status.id', '=', 'products.status_id')
            ->leftJoin('categories as category', 'category.id', '=', 'products.category_id')
            ->select(['products.id', 'products.store_id', 'products.category_id','products.name',
                DB::raw("CASE WHEN products.brand IS NOT NULL AND products.brand != '' THEN products.brand ELSE 'No Brand' END AS brand"),
                'products.slug', 'products.short_description','products.description', 'products.manufacturer',
                'products.status_id', 'status.description as status', 'products.is_featured', 'products.price',
                'products.stock_qty', 'products.qty',
                DB::raw("CASE WHEN COALESCE(CAST(NULLIF(products.qty, '') AS INTEGER), 0) > 0 THEN true ELSE false END AS inStock"),
                'products.tax', 'products.list_price', 'products.discount as discount_price', 'products.is_active',
                'products.sort_order', 'products.published_at', 'products.created_at', 'products.updated_at',
                'seller_store_settings.store_name as store_name', 'category.name as category_name',
            ])->where('products.id', $id)->first();

        if (!$product) {
            abort(404, 'Product not found.');
        }

        $variants = DB::table('product_variants')->where('product_id', $product->id)
            ->select(['product_variants.id','product_variants.product_id','product_variants.name','product_variants.sku',
                'product_variants.barcode','product_variants.price','product_variants.compare_at_price','product_variants.cost_price',
                'product_variants.weight','product_variants.length','product_variants.width','product_variants.height',
                'product_variants.stock_qty','product_variants.manage_stock','product_variants.is_active',
                'product_variants.is_default','product_variants.created_at','product_variants.updated_at',
            ])->orderByDesc('is_default')->orderBy('id')->get();

        // Variant IDs
        $variantIds = $variants->pluck('id');
        $variantAttributes = collect();

        if ($variantIds->isNotEmpty()) {
            $variantAttributes = DB::table('product_variant_attribute_values')
                ->leftJoin('attributes', 'attributes.id', '=', 'product_variant_attribute_values.attribute_id')
                ->leftJoin('attribute_values', 'attribute_values.id', '=', 'product_variant_attribute_values.attribute_value_id')
                ->whereIn('product_variant_attribute_values.product_variant_id', $variantIds)
                ->select(['product_variant_attribute_values.id', 'product_variant_attribute_values.product_variant_id',
                    'product_variant_attribute_values.attribute_id', 'product_variant_attribute_values.attribute_value_id',
                    'attributes.name as attribute_name','attribute_values.value as attribute_value',
                ])->get()->groupBy('product_variant_id');
        }

        $variants->transform(function ($variant) use ($variantAttributes) {
            $variant->attributes = $variantAttributes->get($variant->id, collect())->values();
            return $variant;
        });

        $questions = DB::table('product_questions')->where('product_id', $product->id)
            ->select(['product_questions.id','product_questions.product_id','product_questions.user_id',
                'product_questions.seller_id','product_questions.question','product_questions.answer',
                'product_questions.answered_at','product_questions.is_approved','product_questions.created_at',
                'product_questions.updated_at',
            ])->orderByDesc('id')->get();

        $images = DB::table('product_images')->where('product_id', $product->id)
            ->select(['product_images.id', 'product_images.product_id', 'product_images.product_variant_id',
                'product_images.image_path', 'product_images.alt_text', 'product_images.sort_order',
                'product_images.is_primary', 'product_images.created_at', 'product_images.updated_at',
            ])->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($image) {
                $image->image_url = $image->image_path ? asset('storage/' . $image->image_path) : null;
                return $image;
            });
            $reviews = DB::table('reviews')
                ->leftJoin('users', 'users.id', '=', 'reviews.user_id')
                ->where('reviews.product_id', $product->id)->where('reviews.is_approved', false)
                ->select('reviews.id','reviews.user_id','reviews.product_variant_id','reviews.rating',
                    'reviews.title','reviews.review','reviews.is_verified_purchase','reviews.created_at',
                    'users.name as user_name'
                )->orderByDesc('reviews.created_at')->get();

            foreach ($reviews as $review) {
                $review->images = DB::table('review_images')->where('review_id', $review->id)->orderBy('id')->get();
            }

            $product->reviews = $reviews;

            $product->questions = DB::table('product_questions')->where('product_id', $product->id)->where('is_approved', true)
                ->select('id','product_id','user_id','seller_id',
                    'question','answer','answered_at','is_approved',
                    'created_at','updated_at'
                )->orderByDesc('created_at')->get();

        $product->variants = $variants;
        $product->questions = $questions;
        $product->images = $images;

        return $product;
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $slug = $this->generateUniqueSlug(
                $data['slug'] ?? $data['name']
            );

            $productId = DB::table('products')->insertGetId([
                'store_id' => $data['store_id'],
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'slug' => $slug,
                'price' => $data['price'],
                'purchase_price' => $data['purchase_price'],
                'discount' => $data['discount_price'] ?? null,
                'discount_percentage' => $data['discount_percentage'] ?? null,
                'tax' => $data['tax'] ?? null,
                'qty' => $data['stock_qty'] ?? 0,
                'stock_qty' => $data['stock_qty'] ?? 0,
                'list_price' => $data['list_price'] ?? 0,
                'return_days' => $data['return_days'] ?? null,
                'returnable' => $data['returnable'] ?? false,
                'is_flash_sale' => $data['is_flash_sale'] ?? false,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'product_code' => $data['product_code'] ?? null,
                'model_number' => $data['model_number'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'brand' => $data['brand'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'is_featured' => $data['is_featured'] ?? false,
                'is_deal' => $data['is_deal'] ?? false,
                'deal_start_at' => $data['deal_start_at'] ?? null,
                'deal_end_at' => $data['deal_end_at'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
                'published_at' => $data['published_at'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $variantId = DB::table('product_variants')->insertGetId([
                        'product_id' => $productId,
                        'name' => $variant['name'] ?? null,
                        'sku' => $variant['sku'] ?? null,
                        'barcode' => $variant['barcode'] ?? null,
                        'price' => $variant['price'] ?? 0,
                        'compare_at_price' => $variant['compare_at_price'] ?? null,
                        'cost_price' => $variant['cost_price'] ?? null,
                        'weight' => $variant['weight'] ?? null,
                        'length' => $variant['length'] ?? null,
                        'width' => $variant['width'] ?? null,
                        'height' => $variant['height'] ?? null,
                        'stock_qty' => $variant['stock_qty'] ?? 0,
                        'manage_stock' => $variant['manage_stock'] ?? false,
                        'is_active' => $variant['is_active'] ?? true,
                        'is_default' => $variant['is_default'] ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if (!empty($variant['attributes'])) {
                        foreach ($variant['attributes'] as $attribute) {
                            DB::table('product_variant_attribute_values')->insert([
                                'product_variant_id' => $variantId,
                                'attribute_id' => $attribute['attribute_id'],
                                'attribute_value_id' => $attribute['attribute_value_id'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            if (!empty($data['questions'])) {
                foreach ($data['questions'] as $question) {
                    DB::table('product_questions')->insert([
                        'product_id' => $productId,
                        'user_id' => $question['user_id'] ?? null,
                        'seller_id' => $question['seller_id'] ?? null,
                        'question' => $question['question'],
                        'answer' => $question['answer'] ?? null,
                        'answered_at' => $question['answered_at'] ?? null,
                        'is_approved' => $question['is_approved'] ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (!empty($data['images'])) {
                foreach ($data['images'] as $index => $image) {
                    if ($image instanceof UploadedFile) {
                        $imagePath = $image->store(
                            'products',
                            'public'
                        );

                        DB::table('product_images')->insert([
                            'product_id' => $productId,
                            'product_variant_id' => null,
                            'image_path' => $imagePath,
                            'alt_text' => null,
                            'sort_order' => $index,
                            'is_primary' => $index === 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        continue;
                    }

                    if (is_array($image)) {
                        $imagePath = $image['image_path'] ?? null;
                        if (isset($image['file']) && $image['file'] instanceof UploadedFile) {
                            $imagePath = $image['file']->store(
                                'products',
                                'public'
                            );
                        }

                        if ($imagePath) {
                            DB::table('product_images')->insert([
                                'product_id' => $productId,
                                'product_variant_id' => $image['product_variant_id'] ?? null,
                                'image_path' => $imagePath,
                                'alt_text' => $image['alt_text'] ?? null,
                                'sort_order' => $image['sort_order'] ?? $index,
                                'is_primary' => $image['is_primary'] ?? ($index === 0),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            return Product::findOrFail($productId);
        });
    }

    private function generateUniqueSlug(string $name, ?int $ignoreProductId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (
            DB::table('products')->where('slug', $slug)
                ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {

            if (isset($data['name']) && !isset($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug(
                    $data['name'],
                    $product->id
                );
            }

            $product->update([
                'store_id'            => $data['store_id'] ?? $product->store_id,
                'category_id'         => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
                'brand_id'            => array_key_exists('brand_id', $data) ? $data['brand_id'] : $product->brand_id,
                'name'                => $data['name'] ?? $product->name,
                'slug'                => $data['slug'] ?? $product->slug,
                'price'               => $data['price'] ?? $product->price,
                'discount_price'      => array_key_exists('discount_price', $data) ? $data['discount_price'] : $product->discount_price,
                'discount_percentage' => array_key_exists('discount_percentage', $data) ? $data['discount_percentage'] : $product->discount_percentage,
                'tax'                 => array_key_exists('tax', $data) ? $data['tax'] : $product->tax,
                'stock_qty'           => array_key_exists('stock_qty', $data) ? $data['stock_qty'] : $product->stock_qty,
                'return_days'         => array_key_exists('return_days', $data) ? $data['return_days'] : $product->return_days,
                'returnable'          => array_key_exists('returnable', $data) ? $data['returnable'] : $product->returnable,
                'is_flash_sale'       => array_key_exists('is_flash_sale', $data) ? $data['is_flash_sale'] : $product->is_flash_sale,
                'low_stock_threshold' => array_key_exists('low_stock_threshold', $data) ? $data['low_stock_threshold'] : $product->low_stock_threshold,
                'short_description'   => array_key_exists('short_description', $data) ? $data['short_description'] : $product->short_description,
                'description'         => array_key_exists('description', $data) ? $data['description'] : $product->description,
                'product_code'        => array_key_exists('product_code', $data) ? $data['product_code'] : $product->product_code,
                'model_number'        => array_key_exists('model_number', $data) ? $data['model_number'] : $product->model_number,
                'manufacturer'        => array_key_exists('manufacturer', $data) ? $data['manufacturer'] : $product->manufacturer,
                'status_id'           => $data['status_id'] ?? $product->status_id,
                'is_featured'         => $data['is_featured'] ?? $product->is_featured,
                'is_deal'             => $data['is_deal'] ?? $product->is_deal,
                'brand'               => $data['brand'] ?? $product->brand,
                'deal_start_at'       => array_key_exists('deal_start_at', $data) ? $data['deal_start_at'] : $product->deal_start_at,
                'deal_end_at'         => array_key_exists('deal_end_at', $data) ? $data['deal_end_at'] : $product->deal_end_at,
                'is_active'           => $data['is_active'] ?? $product->is_active,
                'sort_order'          => $data['sort_order'] ?? $product->sort_order,
                'published_at'        => array_key_exists('published_at', $data) ? $data['published_at'] : $product->published_at,
                'updated_at'          => now(),
            ]);

            // ---------- Variants Handling (null fully allowed) ----------
            if (array_key_exists('variants', $data)) {

                // Agar null aaya ya empty array aaya → saare variants delete kar do (default bhi)
                if (is_null($data['variants']) || (is_array($data['variants']) && empty($data['variants']))) {

                    $variantIds = DB::table('product_variants')
                        ->where('product_id', $product->id)
                        ->pluck('id');

                    if ($variantIds->isNotEmpty()) {
                        DB::table('product_variant_attribute_values')
                            ->whereIn('product_variant_id', $variantIds)
                            ->delete();
                    }

                    DB::table('product_variants')
                        ->where('product_id', $product->id)
                        ->delete();
                }
                // Agar variants array mein data hai to normal update/create
                elseif (is_array($data['variants'])) {

                    foreach ($data['variants'] as $variant) {

                        $variantId = null;

                        // Pehle id se check
                        if (!empty($variant['id'])) {
                            $existing = DB::table('product_variants')
                                ->where('id', $variant['id'])
                                ->where('product_id', $product->id)
                                ->first();

                            if ($existing) {
                                $variantId = $existing->id;
                            }
                        }

                        // Agar id nahi mili to SKU se check
                        if (!$variantId && !empty($variant['sku'])) {
                            $existing = DB::table('product_variants')
                                ->where('product_id', $product->id)
                                ->where('sku', $variant['sku'])
                                ->first();

                            if ($existing) {
                                $variantId = $existing->id;
                            }
                        }

                        // ---------- UPDATE existing ----------
                        if ($variantId) {
                            DB::table('product_variants')
                                ->where('id', $variantId)
                                ->where('product_id', $product->id)
                                ->update([
                                    'name'             => array_key_exists('name', $variant) ? $variant['name'] : DB::raw('name'),
                                    'sku'              => array_key_exists('sku', $variant) ? $variant['sku'] : DB::raw('sku'),
                                    'barcode'          => array_key_exists('barcode', $variant) ? $variant['barcode'] : DB::raw('barcode'),
                                    'price'            => array_key_exists('price', $variant) ? $variant['price'] : DB::raw('price'),
                                    'compare_at_price' => array_key_exists('compare_at_price', $variant) ? $variant['compare_at_price'] : DB::raw('compare_at_price'),
                                    'cost_price'       => array_key_exists('cost_price', $variant) ? $variant['cost_price'] : DB::raw('cost_price'),
                                    'stock_qty'        => array_key_exists('stock_qty', $variant) ? $variant['stock_qty'] : DB::raw('stock_qty'),
                                    'weight'           => array_key_exists('weight', $variant) ? $variant['weight'] : DB::raw('weight'),
                                    'length'           => array_key_exists('length', $variant) ? $variant['length'] : DB::raw('length'),
                                    'width'            => array_key_exists('width', $variant) ? $variant['width'] : DB::raw('width'),
                                    'height'           => array_key_exists('height', $variant) ? $variant['height'] : DB::raw('height'),
                                    'manage_stock'     => array_key_exists('manage_stock', $variant) ? $variant['manage_stock'] : DB::raw('manage_stock'),
                                    'is_active'        => array_key_exists('is_active', $variant) ? $variant['is_active'] : DB::raw('is_active'),
                                    'is_default'       => array_key_exists('is_default', $variant) ? $variant['is_default'] : DB::raw('is_default'),
                                    'updated_at'       => now(),
                                ]);
                        }
                        // ---------- CREATE new ----------
                        else {
                            if (!empty($variant['sku'])) {
                                $skuExists = DB::table('product_variants')
                                    ->where('sku', $variant['sku'])
                                    ->exists();

                                if ($skuExists) {
                                    throw new \Exception("SKU '{$variant['sku']}' is already used by another variant.");
                                }
                            }

                            $variantId = DB::table('product_variants')->insertGetId([
                                'product_id'       => $product->id,
                                'name'             => $variant['name'] ?? null,
                                'sku'              => $variant['sku'] ?? null,
                                'barcode'          => $variant['barcode'] ?? null,
                                'price'            => $variant['price'] ?? 0,
                                'compare_at_price' => $variant['compare_at_price'] ?? null,
                                'cost_price'       => $variant['cost_price'] ?? null,
                                'stock_qty'        => $variant['stock_qty'] ?? 0,
                                'weight'           => $variant['weight'] ?? null,
                                'length'           => $variant['length'] ?? null,
                                'width'            => $variant['width'] ?? null,
                                'height'           => $variant['height'] ?? null,
                                'manage_stock'     => $variant['manage_stock'] ?? false,
                                'is_active'        => $variant['is_active'] ?? true,
                                'is_default'       => $variant['is_default'] ?? false,
                                'created_at'       => now(),
                                'updated_at'       => now(),
                            ]);
                        }

                        // Attributes
                        if (array_key_exists('attributes', $variant)) {
                            DB::table('product_variant_attribute_values')
                                ->where('product_variant_id', $variantId)
                                ->delete();

                            foreach ($variant['attributes'] ?? [] as $attr) {
                                if (empty($attr['attribute_id']) || empty($attr['attribute_value_id'])) {
                                    continue;
                                }

                                DB::table('product_variant_attribute_values')->insert([
                                    'product_variant_id' => $variantId,
                                    'attribute_id'       => $attr['attribute_id'],
                                    'attribute_value_id' => $attr['attribute_value_id'],
                                    'created_at'         => now(),
                                    'updated_at'         => now(),
                                ]);
                            }
                        }
                    }
                }
            }

            // Questions
            if (array_key_exists('questions', $data)) {
                DB::table('product_questions')
                    ->where('product_id', $product->id)
                    ->delete();

                foreach ($data['questions'] ?? [] as $question) {
                    DB::table('product_questions')->insert([
                        'product_id'  => $product->id,
                        'user_id'     => $question['user_id'] ?? null,
                        'seller_id'   => $question['seller_id'] ?? null,
                        'question'    => $question['question'],
                        'answer'      => $question['answer'] ?? null,
                        'answered_at' => $question['answered_at'] ?? null,
                        'is_approved' => $question['is_approved'] ?? false,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            // New Images
            if (!empty($data['images'])) {
                foreach ($data['images'] as $index => $image) {

                    if ($image instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $image->store('products', 'public');

                        DB::table('product_images')->insert([
                            'product_id'         => $product->id,
                            'product_variant_id' => null,
                            'image_path'         => $imagePath,
                            'alt_text'           => null,
                            'sort_order'         => $index,
                            'is_primary'         => $index === 0,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                        continue;
                    }

                    if (is_array($image)) {
                        $imagePath = $image['image_path'] ?? null;

                        if (isset($image['file']) && $image['file'] instanceof \Illuminate\Http\UploadedFile) {
                            $imagePath = $image['file']->store('products', 'public');
                        }

                        if ($imagePath) {
                            DB::table('product_images')->insert([
                                'product_id'         => $product->id,
                                'product_variant_id' => $image['product_variant_id'] ?? null,
                                'image_path'         => $imagePath,
                                'alt_text'           => $image['alt_text'] ?? null,
                                'sort_order'         => $image['sort_order'] ?? $index,
                                'is_primary'         => $image['is_primary'] ?? ($index === 0),
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]);
                        }
                    }
                }
            }

            // Remove Images
            if (!empty($data['remove_image_ids'])) {
                DB::table('product_images')
                    ->where('product_id', $product->id)
                    ->whereIn('id', $data['remove_image_ids'])
                    ->delete();
            }

            return $product->refresh();
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $images = DB::table('product_images')->where('product_id', $product->id)->get();

            foreach ($images as $image) {
                if (!empty($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }

            DB::table('product_images')->where('product_id', $product->id)->delete();
            $variantIds = DB::table('product_variants')->where('product_id', $product->id)->pluck('id');

            if ($variantIds->isNotEmpty()) {
                DB::table('product_variant_attribute_values')->whereIn('product_variant_id', $variantIds)->delete();
            }

            DB::table('product_variants')->where('product_id', $product->id)->delete();
            DB::table('product_questions')->where('product_id', $product->id)->delete();

            $product->delete();
        });
    }

    public function deleteImage(int $productId, int $imageId): void
    {
        DB::transaction(function () use ($productId, $imageId) {
            $image = DB::table('product_images')->where('id', $imageId)->where('product_id', $productId)->first();

            if (!$image) {
                abort(404, 'Product image not found.');
            }

            if (!empty($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }

            DB::table('product_images')->where('id', $imageId)->where('product_id', $productId)->delete();

            if ($image->is_primary) {
                $nextImage = DB::table('product_images')->where('product_id', $productId)->orderBy('sort_order')->orderBy('id')->first();

                if ($nextImage) {
                    DB::table('product_images')->where('id', $nextImage->id)
                        ->update([
                            'is_primary' => true,
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    public function getBrands()
    {
        return DB::table('brands')->select('id', 'name')->orderBy('name')->get();
    }

    public function getProductDetail(int $id): object
    {
        $product = DB::table('products')
            ->leftJoin('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->leftJoin('master_types as status','status.id','=','products.status_id')
            ->leftJoin('categories as category','category.id','=','products.category_id')
            ->select(['products.id','products.store_id','products.category_id',
                DB::raw("CASE WHEN products.brand IS NOT NULL AND products.brand != '' THEN products.brand ELSE 'No Brand' END AS brand"),
                'products.name','products.slug','products.short_description','products.description',
                'products.product_code','products.model_number','products.manufacturer',
                'products.status_id','status.description as status',
                'products.is_featured','products.price','products.stock_qty','products.qty',
                DB::raw("CASE WHEN COALESCE(CAST(NULLIF(products.qty, '') AS INTEGER), 0) > 0 THEN true ELSE false END AS \"inStock\" "),
                'products.tax','products.list_price', 'products.discount as discount_price',
                'products.is_active','products.sort_order','products.published_at','products.created_at','products.updated_at',
                'seller_store_settings.store_name as store_name', 'seller_store_settings.logo as store_logo',
                'category.name as category_name','category.slug as category_slug',
            ])->where('products.id', $id)->first();

        if (!$product) {
            abort(404, 'Product not found.');
        }

        $variants = DB::table('product_variants')->where('product_id', $product->id)
            ->select([
                'product_variants.id','product_variants.product_id','product_variants.name','product_variants.sku','product_variants.barcode',
                'product_variants.price','product_variants.compare_at_price','product_variants.cost_price',
                'product_variants.weight','product_variants.length','product_variants.width','product_variants.height',
                'product_variants.stock_qty','product_variants.manage_stock','product_variants.is_active','product_variants.is_default',
                'product_variants.created_at','product_variants.updated_at',
            ])->orderByDesc('is_default')->orderBy('id')->get();

        $variantIds = $variants->pluck('id');
        $variantAttributes = collect();

        if ($variantIds->isNotEmpty()) {
            $variantAttributes = DB::table('product_variant_attribute_values')
                ->leftJoin('attributes','attributes.id','=','product_variant_attribute_values.attribute_id')
                ->leftJoin('attribute_values','attribute_values.id','=','product_variant_attribute_values.attribute_value_id')
                ->whereIn('product_variant_attribute_values.product_variant_id', $variantIds)
                ->select(['product_variant_attribute_values.id', 'product_variant_attribute_values.product_variant_id',
                    'product_variant_attribute_values.attribute_id','product_variant_attribute_values.attribute_value_id',
                    'attributes.name as attribute_name','attribute_values.value as attribute_value',
                ])->get()->groupBy('product_variant_id');
        }

        $variants->transform(function ($variant) use ($variantAttributes) {
            $variant->attributes = $variantAttributes->get($variant->id, collect())->values();

            return $variant;
        });

        $questions = DB::table('product_questions')->where('product_id', $product->id)->where('is_approved', false)
            ->select(['product_questions.id','product_questions.product_id','product_questions.user_id',
                'product_questions.seller_id','product_questions.question','product_questions.answer',
                'product_questions.answered_at','product_questions.is_approved','product_questions.created_at',
                'product_questions.updated_at',
            ])->orderByDesc('created_at')->get();

        $images = DB::table('product_images')->where('product_id', $product->id)
            ->select(['product_images.id','product_images.product_id','product_images.product_variant_id',
                'product_images.image_path','product_images.alt_text','product_images.sort_order',
                'product_images.is_primary','product_images.created_at','product_images.updated_at',
            ])->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($image) {
                $image->image_url = $image->image_path ? asset('storage/' . $image->image_path) : null;
                return $image;
            });

        $reviews = DB::table('reviews')
            ->leftJoin('users','users.id','=','reviews.user_id')
            ->where('reviews.product_id', $product->id)->where('reviews.is_approved', false)
            ->select(['reviews.id','reviews.user_id','reviews.product_variant_id','reviews.rating',
                'reviews.title','reviews.review','reviews.is_verified_purchase','reviews.created_at',
                'users.name as user_name',
            ])->orderByDesc('reviews.created_at')->get();

        foreach ($reviews as $review) {
            $review->images = DB::table('review_images')->where('review_id', $review->id)
                ->select(['id','review_id','image_path','alt_text',])->orderBy('id')->get()
                ->map(function ($image) {
                    $image->image_url = $image->image_path ? asset('storage/' . $image->image_path) : null;
                    return $image;
                });
        }

        $ratingData = DB::table('reviews')->where('product_id', $product->id)->where('is_approved', false)
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating, COUNT(*) as reviews_count')->first();

        $product->rating = round((float) ($ratingData->average_rating ?? 0), 1);
        $product->reviews_count = (int) ($ratingData->reviews_count ?? 0);
        $product->variants = $variants;
        $product->questions = $questions;
        $product->images = $images;
        $product->reviews = $reviews;

        return $product;
    }

    public function storeReview(int $productId, array $data, array $files = [])
    {
        if (!auth()->check()) {
            abort(401, 'Please login to submit a review.');
        }

        return DB::transaction(function () use ($productId, $data, $files) {
            $userId = auth()->id();
            $productExists = DB::table('products')->where('id', $productId)->exists();

            if (!$productExists) {
                abort(404, 'Product not found.');
            }

            $alreadyReviewed = DB::table('reviews')->where('user_id', $userId)->where('product_id', $productId)->exists();

            if ($alreadyReviewed) {
                abort(422, 'You have already reviewed this product.');
            }

            $reviewId = DB::table('reviews')->insertGetId([
                'user_id' => $userId,
                'product_id' => $productId,
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'order_item_id' => $data['order_item_id'] ?? null,
                'rating' => $data['rating'],
                'title' => $data['name'] ?? null,
                'review' => $data['review'],
                'is_verified_purchase' => false,
                'is_approved' => false,
                'approved_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($files as $file) {

                if (!$file) {
                    continue;
                }

                $path = $file->store('reviews/' . $reviewId, 'public');

                DB::table('review_images')->insert([
                    'review_id' => $reviewId,
                    'image_path' => $path,
                    'alt_text' => $data['name'] ?? null,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return DB::table('reviews')->where('id', $reviewId)->first();
        });
    }

    public function storeQuestion(int $productId, array $data)
    {
        if (!Auth::guard('web')->check()) {
            abort(401, 'Please login to ask a question.');
        }

        $userId = Auth::guard('web')->id();
        $product = DB::table('products')
            ->join('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->select('products.id','seller_store_settings.seller_id')->where('products.id', $productId)->first();

        if (!$product) {
            abort(404, 'Product not found.');
        }

        $questionId = DB::table('product_questions')->insertGetId([
            'product_id' => $productId,
            'user_id' => $userId,
            'seller_id' => $product->seller_id,
            'question' => $data['question'],
            'answer' => null,
            'answered_at' => null,
            'is_approved' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('product_questions')->where('id', $questionId)->first();
    }

    public function getSellerQuestions()
    {
        $sellerId = Auth::user()->id;

        return DB::table('product_questions')
            ->join('products','products.id','=','product_questions.product_id')
            ->join('users as buyer','buyer.id','=','product_questions.user_id')
            ->join('users as seller','seller.id','=','product_questions.seller_id')
            ->where('product_questions.seller_id', $sellerId)
            ->select('product_questions.id','product_questions.product_id','product_questions.user_id',
                'product_questions.seller_id','product_questions.question','product_questions.answer',
                'product_questions.answered_at','product_questions.created_at','product_questions.updated_at',
                'products.name as product_name','buyer.name as buyer_name','seller.name as seller_name'
            )->orderByDesc('product_questions.created_at')->get();
    }

    public function answerQuestion(int $questionId, string $answer)
    {
        $sellerId = Auth::user()->id;
        $question = DB::table('product_questions')->where('id', $questionId)->where('seller_id', $sellerId)->first();

        if (!$question) {
            abort(404, 'Question not found.');
        }

        DB::table('product_questions')->where('id', $questionId)->where('seller_id', $sellerId)
            ->update(['answer' => $answer,'answered_at' => now(),'updated_at' => now(),]);

        return DB::table('product_questions')->where('id', $questionId)->first();
    }

    public function getSellerReviews()
    {
        $sellerId = auth()->id();

        $productIds = DB::table('products')
            ->join('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->where('seller_store_settings.seller_id', $sellerId)->pluck('products.id');

        $reviews = DB::table('reviews')
            ->join('users','users.id','=','reviews.user_id')->join('products','products.id','=','reviews.product_id')
            ->whereIn('reviews.product_id', $productIds)
            ->select(['reviews.id','reviews.product_id','products.name as product_name',
                'reviews.user_id','users.name as user_name','reviews.rating','reviews.title',
                'reviews.review','reviews.review_answer','reviews.is_verified_purchase','reviews.created_at',
            ])->orderByDesc('reviews.created_at')->get();

        $summary = DB::table('reviews')->whereIn('reviews.product_id', $productIds)
            ->selectRaw('COUNT(*) as total_reviews')
            ->selectRaw('COALESCE(AVG(rating), 0) as avg_rating')
            ->selectRaw('COUNT(*) FILTER (WHERE rating = 5) as five_star')
            ->first();

        return [
            'summary' => [
                'total_reviews' => (int) $summary->total_reviews,
                'avg_rating' => round((float) $summary->avg_rating, 1),
                'five_star' => (int) $summary->five_star,
            ],

            'reviews' => $reviews,
        ];
    }

    public function answerReview(int $reviewid, string $answer)
    {
        $sellerId = Auth::user()->id;

        $review = DB::table('reviews')
            ->join('products','products.id','=','reviews.product_id')
            ->join('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->where('reviews.id', $reviewid)->where('seller_store_settings.seller_id', $sellerId)
            ->select(['reviews.id','reviews.product_id','seller_store_settings.seller_id',])->first();

        if (!$review) {
            abort(404, 'Review not found.');
        }

        DB::table('reviews')->where('id', $reviewid)
            ->update(['seller_id' => $sellerId,'review_answer' => $answer,'updated_at' => now(),]);

        return DB::table('reviews')->where('id', $reviewid)->first();
    }

    public function getBuyerProducts()
    {
        $products = DB::table('products')
            ->leftJoin('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->leftJoin('categories','categories.id','=','products.category_id')
            ->leftJoin('master_types as status','status.id','=','products.status_id')
            ->select(['products.id','products.product_code','products.name','products.slug',
                'products.category_id','categories.name as category_name',
                'products.is_deal','products.deal_start_at','products.deal_end_at',
                DB::raw("CASE WHEN products.brand IS NOT NULL AND products.brand != '' THEN products.brand ELSE 'No Brand' END AS brand"),
                'products.store_id','seller_store_settings.store_name',
                'products.price','products.list_price','products.discount','products.tax',
                'products.is_featured','products.created_at','products.is_active','products.published_at',
                DB::raw("CASE WHEN products.brand IS NOT NULL AND products.brand != '' THEN products.brand ELSE 'No Brand' END AS brand_name"),
                DB::raw("
                    (SELECT CONCAT( '" . config('app.url') . "/storage/', pi.image_path) FROM product_images pi
                        WHERE pi.product_id = products.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
                "),
                DB::raw("COALESCE((SELECT ROUND(AVG(r.rating)::numeric, 1) FROM reviews r WHERE r.product_id = products.id), 0) AS rating"),
                DB::raw("(SELECT COUNT(*) FROM reviews r WHERE r.product_id = products.id) AS reviews_count"),
                DB::raw("CASE WHEN COALESCE(CAST(NULLIF(products.qty, '') AS INTEGER), 0) > 0 THEN true ELSE false END AS inStock"),
            ])->where('products.is_active', true)->whereNotNull('products.published_at')->where('status.description', 'Active')
            ->orderByDesc('products.id')->get();

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $hotProductIds = DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')
            ->whereBetween('orders.created_at', [$startOfWeek, $endOfWeek,])
            ->whereNotIn('orders.status_id', function ($query) {
                $query->select('id')->from('master_types')->where('key', 'order_status')->whereIn('value', ['cancelled',]);
            })->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as sold_quantity'))
            ->groupBy('order_items.product_id')->orderByDesc('sold_quantity')->get();

        $hotProducts = collect();

        foreach ($hotProductIds as $hot) {
            $product = $products->firstWhere('id', $hot->product_id);

            if ($product) {
                $product->sold_quantity = (int) $hot->sold_quantity;
                $hotProducts->push($product);
            }
        }

        $newArrivals = $products
            ->filter(function ($product) use ($startOfWeek, $endOfWeek) {
                return $product->created_at >= $startOfWeek && $product->created_at <= $endOfWeek;
            })->sortByDesc('created_at')->values();

        return [
            'featured_products' => $products->where('is_featured', true)->values(),
            'deals_of_the_day' => $products->where('is_deal', true)
                ->filter(function ($product) {
                    return $product->deal_start_at <= now() && $product->deal_end_at >= now();
                })->values(),
            'hot_products' => $hotProducts->values(),
            'new_arrivals' => $newArrivals,
            'products' => $products->where('is_featured', false)->values(),
        ];
    }

    public function reviewLike(array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to like a review.');
        }

        $userId = auth()->id();

        return DB::transaction(function () use ($data, $userId) {
            $reviewId = $data['review_id'];
            $review = DB::table('reviews')->where('id', $reviewId)->first();

            if (!$review) {
                abort(404, 'Review not found.');
            }

            if ($data['case'] === 'like') {
                $existingLike = DB::table('review_likes')->where('review_id', $reviewId)->where('user_id', $userId)->exists();

                if (!$existingLike) {
                    DB::table('review_likes')->insert([
                        'review_id' => $reviewId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $liked = true;
            }
            else {
                DB::table('review_likes')->where('review_id', $reviewId)->where('user_id', $userId)->delete();
                $liked = false;
            }

            $likesCount = DB::table('review_likes')->where('review_id', $reviewId)->count();

            return [
                'review_id' => $reviewId,
                'liked' => $liked,
                'likes_count' => $likesCount,
            ];
        });
    }

}
