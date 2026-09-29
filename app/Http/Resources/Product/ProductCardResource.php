<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,

            'image' => $this->image_path
                ? asset('storage/' . $this->image_path)
                : null,

            'price' => $this->price,
            'discount_price' => $this->discount_price,

            // review table confirm hone ke baad:
            // 'review' => $this->review,
            // 'reviews_count' => $this->reviews_count,
        ];
    }
}
