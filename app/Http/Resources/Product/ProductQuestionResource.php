<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'seller_id' => $this->seller_id,
            'question' => $this->question,
            'answer' => $this->answer,
            'answered_at' => $this->answered_at,
            'is_approved' => (bool) $this->is_approved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
