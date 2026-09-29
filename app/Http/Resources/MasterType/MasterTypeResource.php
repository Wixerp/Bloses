<?php

namespace App\Http\Resources\MasterType;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
            'description' => $this->description,
        ];
    }
}
