<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],

            'store_id' => [
                'nullable',
                'integer',
                'exists:seller_store_settings,id'
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id'
            ],

            'status_id' => ['nullable', 'integer'],

            'brand_id' => ['nullable', 'integer'],

            'stock' => [
                'nullable',
                'in:in_stock,low_stock,out_of_stock'
            ],

            'is_featured' => ['nullable', 'boolean'],

            'is_active' => ['nullable', 'boolean'],

            'min_price' => ['nullable', 'numeric', 'min:0'],

            'max_price' => ['nullable', 'numeric', 'min:0'],

            'date_from' => ['nullable', 'date'],

            'date_to' => ['nullable', 'date'],

            'sort_by' => [
                'nullable',
                'in:name,price,stock,created_at'
            ],

            'sort_order' => [
                'nullable',
                'in:asc,desc'
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ],
        ];
    }
}
