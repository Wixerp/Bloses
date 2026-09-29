<?php

namespace App\Http\Resources\Seller;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerRegistrationStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'registered' => $this->resource,
        ];
    }
}
