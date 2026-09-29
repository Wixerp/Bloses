<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,
            'slug' => $this->slug,

            'product_code' => $this->product_code,
            'model_number' => $this->model_number,
            'manufacturer' => $this->manufacturer,

            'seller' => [
                'id' => $this->seller_id,
                'name' => $this->seller_name,
            ],

            'store' => [
                'id' => $this->store_id,
                'name' => $this->store_name,
            ],

            'category' => [
                'id' => $this->category_id,
                'name' => $this->category_name,
            ],

            'variant' => [
                'sku' => $this->sku,
                'price' => $this->price,
                'stock_qty' => $this->stock_qty,
            ],

            'status_id' => $this->status_id,

            'is_featured' => (bool) $this->is_featured,

            'is_active' => (bool) $this->is_active,

            'published_at' => $this->published_at,

            'created_at' => $this->created_at,
        ];
    }
}
