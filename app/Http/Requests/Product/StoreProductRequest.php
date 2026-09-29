<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->category_id === 'null' ? null : $this->category_id,
            'status_id' => $this->status_id === 'null' ? null : $this->status_id,
            'is_featured' => filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }

    public function rules(): array
    {
        return [
            'store_id'              => ['required', 'integer', 'exists:seller_store_settings,id'],
            'category_id'           => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'              => ['nullable', 'integer', 'exists:brands,id'],
            'name'                  => ['required', 'string', 'max:255'],
            'slug'                  => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')],
            'price'                 => ['required', 'numeric', 'min:0'],
            'list_price'            => ['nullable', 'numeric', 'min:0'],
            'purchase_price'        => ['nullable', 'numeric', 'min:0'],
            'discount_price'        => ['nullable', 'numeric', 'min:0'],
            'discount_percentage'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax'                   => ['nullable', 'numeric', 'min:0'],
            'stock_qty'             => ['nullable', 'integer', 'min:0'],
            'return_days'           => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold'   => ['nullable', 'integer', 'min:0'],
            'short_description'     => ['nullable', 'string',  'max:500'],
            'description'           => ['nullable', 'string'],
            'product_code'          => ['nullable', 'string', 'max:100', Rule::unique('products', 'product_code')],
            'model_number'          => ['nullable', 'string', 'max:100'],
            'manufacturer'          => ['nullable', 'string', 'max:255'],
            'status_id'             => ['required', 'integer'],
            'returnable'            => ['nullable', 'boolean'],
            'is_flash_sale'         => ['nullable', 'boolean'],
            'is_featured'           => ['nullable', 'boolean'],
            'is_deal'               => ['nullable', 'boolean'],
            'deal_start_at'         => ['nullable', 'string', 'max:100'],
            'deal_end_at'           => ['nullable', 'string', 'max:100'],
            'is_active'             => ['nullable', 'boolean'],
            'sort_order'            => ['nullable', 'integer', 'min:0'],
            'published_at'          => ['nullable', 'date'],

            // ========== VARIANTS – completely optional ==========
            'variants'                              => ['nullable', 'array'],
            'variants.*.name'                       => ['nullable', 'string', 'max:255'],
            'variants.*.sku'                        => ['nullable','string','max:100','distinct',Rule::unique('product_variants', 'sku'),],
            'variants.*.barcode'                    => ['nullable','string','max:100','distinct',Rule::unique('product_variants', 'barcode'),],
            'variants.*.price'                      => ['nullable', 'numeric', 'min:0'], // ← was required, now optional
            'variants.*.compare_at_price'           => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price'                 => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_qty'                  => ['nullable', 'integer', 'min:0'],
            'variants.*.weight'                     => ['nullable', 'numeric', 'min:0'],
            'variants.*.length'                     => ['nullable', 'numeric', 'min:0'],
            'variants.*.width'                      => ['nullable', 'numeric', 'min:0'],
            'variants.*.height'                     => ['nullable', 'numeric', 'min:0'],
            'variants.*.manage_stock'               => ['nullable', 'boolean'],
            'variants.*.is_active'                  => ['nullable', 'boolean'],
            'variants.*.is_default'                 => ['nullable', 'boolean'],

            // attributes bhi optional
            'variants.*.attributes'                         => ['nullable', 'array'],
            'variants.*.attributes.*.attribute_id'          => ['nullable', 'integer', 'exists:attributes,id'],
            'variants.*.attributes.*.attribute_value_id'    => ['nullable', 'integer', 'exists:attribute_values,id'],

            // Images
            'images'        => ['nullable', 'array', 'max:10'],
            'images.*'      => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Store is required.',
            'store_id.exists' => 'Selected store does not exist.',
            'category_id.exists' => 'Selected category does not exist.',
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name may not exceed 255 characters.',
            'slug.unique' => 'This product slug is already in use.',
            'product_code.unique' => 'This product code is already in use.',
            'variants.array' => 'Variants must be provided as an array.',
            'variants.*.sku.unique'     => 'This SKU is already in use.',
            'variants.*.sku.distinct'   => 'Duplicate SKU is not allowed in the same request.',
            'variants.*.barcode.unique' => 'This barcode is already in use.',
            'variants.*.barcode.distinct' => 'Duplicate barcode is not allowed in the same request.',
            'variants.*.price.required' => 'Variant price is required.',
            'variants.*.price.numeric' => 'Variant price must be a number.',
            'variants.*.price.min' => 'Variant price cannot be negative.',
            'variants.*.attributes.array' => 'Variant attributes must be an array.',
            'variants.*.attributes.*.attribute_id.required' => 'Attribute is required.',
            'variants.*.attributes.*.attribute_id.exists' => 'Selected attribute does not exist.',
            'variants.*.attributes.*.attribute_value_id.required' => 'Attribute value is required.',
            'variants.*.attributes.*.attribute_value_id.exists' => 'Selected attribute value does not exist.',
            'images.array' => 'Images must be provided as an array.',
            'images.max' => 'You may upload a maximum of 10 images.',
            'images.*.required' => 'Product image is required.',
            'images.*.image' => 'Each product image must be a valid image.',
            'images.*.mimes' => 'Product images must be JPG, JPEG, PNG, or WEBP.',
            'images.*.max' => 'Each product image may not exceed 10 MB.',
        ];
    }
}
