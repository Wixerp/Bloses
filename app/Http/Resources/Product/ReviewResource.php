<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user' => [
                'id' => $this->user_id,
                'name' => $this->user_name,
            ],

            'rating' => (int) $this->rating,
            'title' => $this->title,
            'review' => $this->review,

            'is_verified_purchase' =>
                (bool) $this->is_verified_purchase,

            'created_at' => $this->created_at,

            'images' => collect($this->images ?? [])
                ->map(function ($image) {
                    return [
                        'id' => $image->id,

                        'image' => $image->image_path
                            ? asset('storage/' . $image->image_path)
                            : null,

                        'alt_text' => $image->alt_text,
                    ];
                })
                ->values(),
        ];
    }
}
