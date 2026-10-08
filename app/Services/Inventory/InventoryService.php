<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class InventoryService
{
    private function getSellerId()
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        return $sellerId;
    }

    public function index()
    {
        $sellerId = $this->getSellerId();

        $products = DB::table('products')
            ->join('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->where('seller_store_settings.seller_id', $sellerId)
            ->select([
                'products.id','products.product_code','products.name','products.slug','products.store_id',
                'products.qty','products.stock_qty','products.low_stock_threshold','products.is_active',
            ])->orderByDesc('products.id')->get();

        foreach ($products as $product) {
            $product->image = DB::table('product_images')->where('product_id', $product->id)
                ->orderByDesc('is_primary')->orderBy('sort_order')->value('image_path');

            if ($product->image) {
                $product->image = rtrim(config('app.url'), '/') . '/storage/' . ltrim($product->image, '/');
            }

            $product->variants = DB::table('product_variants')->where('product_id', $product->id)
                ->select(['id','product_id','name','sku','barcode','price',
                    'compare_at_price','cost_price','stock_qty','manage_stock','is_default',
                ])->orderByDesc('is_default')->orderBy('id')->get();

            foreach ($product->variants as $variant) {
                $stock = (int) ($variant->stock_qty ?? 0);
                $variant->stock = $stock;
                $variant->out_of_stock = $stock <= 0;
                $variant->low_stock = $stock > 0 && $stock <= (int) ($product->low_stock_threshold ?? 5);
            }

            $productStock = (int) ($product->stock_qty ?? $product->qty ?? 0);
            $product->stock = $productStock;
            $product->out_of_stock = $productStock <= 0;
            $product->low_stock = $productStock > 0 && $productStock <= (int) ($product->low_stock_threshold ?? 5);

            $product->variant_stock = $product->variants->sum(
                fn ($variant) => (int) ($variant->stock_qty ?? 0)
            );
        }

        return $products;
    }

    public function update(int $productId, ?int $stockQty = null, ?int $variantId = null)
    {
        $sellerId = $this->getSellerId();

        $product = DB::table('products')
            ->join('seller_store_settings','seller_store_settings.id','=','products.store_id')
            ->where('products.id', $productId)->where('seller_store_settings.seller_id', $sellerId)
            ->select([
                'products.id','products.store_id','products.qty','products.stock_qty',
            ])->first();

        if (!$product) {
            abort(404, 'Product not found.');
        }

        if ($variantId !== null) {
            $variant = DB::table('product_variants')->where('id', $variantId)->where('product_id', $productId)->first();

            if (!$variant) {
                abort(404, 'Product variant not found.');
            }

            DB::table('product_variants')->where('id', $variantId)
                ->update([
                    'qty' => $stockQty,
                    'stock_qty' => $stockQty,
                    'updated_at' => now(),
                ]);

            $updatedVariant = DB::table('product_variants')->where('id', $variantId)
                ->select([
                    'id','product_id','name','sku','stock_qty','manage_stock','is_default',
                ])->first();

            return [
                'type' => 'variant',
                'product_id' => $productId,
                'variant' => $updatedVariant,
            ];
        }

        DB::table('products')->where('id', $productId)
            ->update([
                'qty' => $stockQty,
                'stock_qty' => $stockQty,
                'updated_at' => now(),
            ]);


        $updatedProduct = DB::table('products')
            ->where('id', $productId)
            ->select([
                'id','product_code','name','qty','stock_qty','low_stock_threshold',
            ])->first();

        $stock = (int) ($updatedProduct->stock_qty ?? 0);

        return [
            'type' => 'product',
            'product' => $updatedProduct,
            'stock' => $stock,
            'out_of_stock' => $stock <= 0,
            'low_stock' => $stock > 0 && $stock <= (int) ($updatedProduct->low_stock_threshold ?? 5),
        ];
    }

}
