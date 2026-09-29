<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use App\Models\{Seller};
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->getRoleNames()->first(),
            'sellerProfileStatus' => Seller::where('user_id', $this->id)->where('status_id', 36)->exists(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->toArray(),
        ];
    }
}
