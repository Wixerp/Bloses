<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [];

        if (isset($this->id)) {
            $data['id'] = $this->id;
        }

        if (isset($this->parent_id)) {
            $data['parent_id'] = $this->parent_id;
        }

        if (isset($this->name)) {
            $data['name'] = $this->name;
        }

        if (isset($this->slug)) {
            $data['slug'] = $this->slug;
        }

        if (isset($this->description)) {
            $data['description'] = $this->description;
        }

        if (isset($this->image)) {
            $data['image'] = $this->image
                ? asset('storage/' . $this->image)
                : null;
        }

        if (isset($this->icon)) {
            $data['icon'] = $this->icon;
        }

        if (isset($this->sort_order)) {
            $data['sort_order'] = $this->sort_order;
        }

        if (isset($this->is_active)) {
            $data['is_active'] = (bool) $this->is_active;
        }

        if (isset($this->is_featured)) {
            $data['is_featured'] = (bool) $this->is_featured;
        }

        if (isset($this->products_count)) {
            $data['products_count'] = (int) $this->products_count;
        }

        if (isset($this->created_at)) {
            $data['created_at'] = $this->created_at;
        }

        if (isset($this->updated_at)) {
            $data['updated_at'] = $this->updated_at;
        }

        return $data;
    }
}
