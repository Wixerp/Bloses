<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
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
            'short_description' => $this->short_description,
            'description' => $this->description,
            'category' => ['id' => $this->category_id, 'name' => $this->category_name, 'slug' => $this->category_slug,],
            'store' => ['id' => $this->store_id, 'name' => $this->store_name,
                'logo' => $this->store_logo ? asset('storage/' . $this->store_logo) : null,
            ],
            'price' => $this->price,
            'list_price' => $this->list_price,
            'discount_price' => $this->discount_price,
            'tax' => $this->tax,
            'stock_qty' => $this->stock_qty,
            'qty' => $this->qty,
            'inStock' => (bool) $this->inStock,
            'images' => collect($this->images)
                ->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image' => $image->image_url ?? ($image->image_path ? asset( 'storage/' . $image->image_path) : null),
                        'alt_text' => $image->alt_text,
                        'is_primary' => (bool) $image->is_primary,
                    ];
                })->values(),

            'variants' => collect($this->variants)
                ->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'price' => $variant->price,
                        'compare_at_price' => $variant->compare_at_price,
                        'cost_price' => $variant->cost_price,
                        'weight' => $variant->weight,
                        'length' => $variant->length,
                        'width' => $variant->width,
                        'height' => $variant->height,
                        'stock_qty' => $variant->stock_qty,
                        'manage_stock' => (bool) $variant->manage_stock,
                        'is_active' => (bool) $variant->is_active,
                        'is_default' => (bool) $variant->is_default,
                        'attributes' => collect($variant->attributes)
                            ->map(function ($attribute) {
                                return [
                                    'attribute_id' => $attribute->attribute_id,
                                    'attribute_name' => $attribute->attribute_name,
                                    'value_id' => $attribute->attribute_value_id,
                                    'value' => $attribute->attribute_value,
                                ];
                            })->values(),
                    ];
                })
                ->values(),


            // =========================
            // RATING
            // =========================
            'rating' => (float) $this->rating,

            'reviews_count' =>
                (int) $this->reviews_count,


            // =========================
            // REVIEWS
            // =========================
            'reviews' => collect($this->reviews)
                ->map(function ($review) {

                    return [
                        'id' => $review->id,

                        'user' => [
                            'id' => $review->user_id,
                            'name' => $review->user_name,
                        ],

                        'rating' => $review->rating,
                        'title' => $review->title,
                        'review' => $review->review,

                        'is_verified_purchase' =>
                            (bool) $review->is_verified_purchase,

                        'created_at' => $review->created_at,

                        'images' => collect($review->images)
                            ->map(function ($image) {

                                return [
                                    'id' => $image->id,

                                    'image' => $image->image_url
                                        ?? (
                                            $image->image_path
                                                ? asset(
                                                    'storage/' .
                                                    $image->image_path
                                                )
                                                : null
                                        ),

                                    'alt_text' =>
                                        $image->alt_text,
                                ];
                            })
                            ->values(),
                    ];
                })
                ->values(),


            // =========================
            // QUESTIONS
            // =========================
            'questions' => collect($this->questions)
                ->map(function ($question) {

                    return [
                        'id' => $question->id,

                        'product_id' =>
                            $question->product_id,

                        'user_id' =>
                            $question->user_id,

                        'seller_id' =>
                            $question->seller_id,

                        'question' =>
                            $question->question,

                        'answer' =>
                            $question->answer,

                        'answered_at' =>
                            $question->answered_at,

                        'is_approved' =>
                            (bool) $question->is_approved,

                        'created_at' =>
                            $question->created_at,

                        'updated_at' =>
                            $question->updated_at,
                    ];
                })
                ->values(),


            // =========================
            // STATUS
            // =========================
            'status_id' => $this->status_id,
            'status' => $this->status,

            'is_featured' =>
                (bool) $this->is_featured,

            'is_active' =>
                (bool) $this->is_active,

            'published_at' =>
                $this->published_at,

            'created_at' =>
                $this->created_at,
        ];
    }
}
