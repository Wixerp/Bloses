<?php

// namespace App\Http\Requests\Product;

// use Illuminate\Foundation\Http\FormRequest;
// use Illuminate\Validation\Rule;

// class UpdateProductRequest extends FormRequest
// {
//     public function authorize(): bool
//     {
//         return auth()->check();
//     }

//     protected function prepareForValidation(): void
//     {
//         $this->merge([
//             'category_id' => $this->category_id === 'null' ? null : $this->category_id,
//             'status_id' => $this->status_id === 'null' ? null : $this->status_id,
//             'brand_id' => $this->brand_id === 'null' ? null : $this->brand_id,
//             'is_featured' => $this->has('is_featured')
//                 ? filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
//             'is_active' => $this->has('is_active')
//                 ? filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
//             'returnable' => $this->has('returnable')
//                 ? filter_var($this->returnable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
//             'is_flash_sale' => $this->has('is_flash_sale')
//                 ? filter_var($this->is_flash_sale, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
//         ]);
//     }

//     public function rules(): array
//     {
//         $productId = $this->route('product');

//         if (is_object($productId)) {
//             $productId = $productId->id;
//         }

//         return [
//             'store_id' => ['sometimes', 'integer', 'exists:seller_store_settings,id',],
//             'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id',],
//             'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id',],
//             'name' => ['sometimes', 'string', 'max:255',],
//             'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId),],
//             'price' => ['sometimes', 'nullable', 'numeric', 'min:0',],
//             'purchase_price' => ['sometimes','nullable','numeric','min:0',],
//             'list_price' => ['sometimes','nullable','numeric','min:0',],
//             'discount_price' => ['sometimes','nullable','numeric','min:0',],
//             'discount_percentage' => ['sometimes','nullable','numeric','min:0','max:100',],
//             'return_days' => ['sometimes','nullable','integer','min:0',],
//             'returnable' => ['sometimes','nullable','boolean',],
//             'is_flash_sale' => ['sometimes','nullable','boolean',],
//             'low_stock_threshold' => ['sometimes','nullable','integer','min:0',],
//             'tax' => ['sometimes','nullable','numeric','min:0',],
//             'stock_qty' => ['sometimes','nullable','integer','min:0',],
//             'short_description' => ['sometimes','nullable','string','max:500',],
//             'description' => ['sometimes','nullable','string','max:10000',],
//             'product_code' => ['sometimes','nullable','string','max:100', Rule::unique('products', 'product_code')->ignore($productId),],
//             'model_number' => ['sometimes','nullable','string','max:100',],
//             'manufacturer' => ['sometimes','nullable','string','max:255',],
//             'status_id' => ['sometimes','integer','exists:master_types,id',],
//             'is_featured' => ['sometimes','boolean',],
//             'is_deal' => ['sometimes','nullable','boolean',],
//             'deal_start_at' => ['sometimes','nullable','string','max:100',],
//             'deal_end_at' => ['sometimes','nullable','string','max:100',],
//             'is_active' => ['sometimes','nullable','boolean',],
//             'sort_order' => ['sometimes','nullable','integer','min:0',],
//             'published_at' => ['sometimes','nullable','date',],

//             // 'variants' => ['sometimes','array', ],
//             'variants' => ['sometimes', 'nullable', 'array'],
//             'variants.*.id' => ['sometimes','nullable','integer','exists:product_variants,id', ],
//             'variants.*.name' => ['sometimes','nullable','string','max:255', ],
//             'variants.*.sku' => [
//                 'sometimes',
//                 'nullable',
//                 'string',
//                 'max:100',
//                 function ($attribute, $value, $fail) {
//                     if ($value === null || $value === '') {
//                         return;
//                     }
//                     if (!preg_match('/variants\.(\d+)\.sku/', $attribute, $matches)) {
//                         return;
//                     }
//                     $index     = $matches[1];
//                     $variantId = $this->input("variants.{$index}.id");
//                     $product = $this->route('product');
//                     $productId = is_object($product) ? $product->id : $product;
//                     $query = \DB::table('product_variants')->where('sku', $value);

//                     if (!empty($variantId)) {
//                         $query->where('id', '!=', $variantId);
//                     }
//                     if ($productId) {
//                         $sameProductSku = \DB::table('product_variants')->where('product_id', $productId)->where('sku', $value)->first();
//                         if ($sameProductSku) {
//                             return;
//                         }
//                     }
//                     if ($query->exists()) {
//                         $fail("The SKU '{$value}' is already taken by another variant.");
//                     }
//                 },
//             ],
//             'variants.*.barcode' => ['sometimes','nullable','string','max:100', ],
//             'variants.*.price' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
//             'variants.*.compare_at_price' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.cost_price' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.stock_quantity' => ['sometimes','nullable','integer','min:0', ],
//             'variants.*.weight' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.length' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.width' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.height' => ['sometimes','nullable','numeric','min:0', ],
//             'variants.*.manage_stock' => ['sometimes','boolean', ],
//             'variants.*.is_active' => ['sometimes','nullable','boolean', ],
//             'variants.*.is_default' => ['sometimes','nullable','boolean', ],
//             'variants.*.attributes' => ['sometimes','nullable','array',],
//             'variants.*.attributes.*.attribute_id' => ['nullable','integer','exists:attributes,id',],
//             'variants.*.attributes.*.attribute_value_id' => ['nullable','integer','exists:attribute_values,id',],

//             'images' => ['sometimes','nullable','array','max:10',],
//             'images.*' => ['required','image','mimes:jpg,jpeg,png,webp','max:5120',],
//             'remove_image_ids' => ['sometimes','nullable','array',],
//             'remove_image_ids.*' => ['integer','nullable','exists:product_images,id',],
//         ];
//     }

//     public function messages(): array
//     {
//         return [
//             'store_id.exists' => 'Selected store does not exist.',
//             'category_id.exists' => 'Selected category does not exist.',
//             'brand_id.exists' => 'Selected brand does not exist.',
//             'name.max' => 'Product name may not exceed 255 characters.',
//             'slug.unique' => 'This product slug is already in use.',
//             'product_code.unique' => 'This product code is already in use.',
//             'status_id.exists' => 'Selected status does not exist.',
//             'price.numeric' => 'Product price must be a number.',
//             'price.min' => 'Product price cannot be negative.',
//             'discount.numeric' => 'Discount must be a number.',
//             'discount.min' => 'Discount cannot be negative.',
//             'discount_percentage.numeric' => 'Discount percentage must be a number.',
//             'discount_percentage.max' => 'Discount percentage cannot exceed 100.',
//             'return_days.integer' => 'Return days must be a whole number.',
//             'return_days.min' => 'Return days cannot be negative.',
//             'low_stock_threshold.integer' => 'Low stock threshold must be a whole number.',
//             'qty.integer' => 'Quantity must be a whole number.',

//             'variants.array' => 'Variants must be provided as an array.',
//             'variants.*.id.exists' => 'Selected product variant does not exist.',
//             'variants.*.price.numeric' => 'Variant price must be a number.',
//             'variants.*.price.min' => 'Variant price cannot be negative.',
//             'variants.*.sku.max' => 'SKU may not exceed 100 characters.',
//             'variants.*.barcode.max' => 'Barcode may not exceed 100 characters.',
//             'variants.*.stock_quantity.integer' => 'Variant stock quantity must be a whole number.',
//             'variants.*.stock_quantity.min' => 'Variant stock quantity cannot be negative.',
//             'variants.*.weight.numeric' => 'Variant weight must be a number.',
//             'variants.*.length.numeric' => 'Variant length must be a number.',
//             'variants.*.width.numeric' => 'Variant width must be a number.',
//             'variants.*.height.numeric' => 'Variant height must be a number.',
//             'variants.*.attributes.array' => 'Variant attributes must be an array.',
//             'variants.*.attributes.*.attribute_id.required' => 'Attribute is required.',
//             'variants.*.attributes.*.attribute_id.exists' => 'Selected attribute does not exist.',
//             'variants.*.attributes.*.attribute_value_id.required' => 'Attribute value is required.',
//             'variants.*.attributes.*.attribute_value_id.exists' => 'Selected attribute value does not exist.',

//             'images.array' => 'Images must be provided as an array.',
//             'images.max' => 'You may upload a maximum of 10 new images.',
//             'images.*.required' => 'Product image is required.',
//             'images.*.image' => 'Each product image must be a valid image.',
//             'images.*.mimes' => 'Product images must be JPG, JPEG, PNG, or WEBP.',
//             'images.*.max' => 'Each product image may not exceed 5 MB.',
//             'remove_image_ids.array' => 'Remove image IDs must be provided as an array.',
//             'remove_image_ids.*.exists' => 'Selected product image does not exist.',
//         ];
//     }
// }

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id'   => $this->category_id === 'null' ? null : $this->category_id,
            'status_id'     => $this->status_id === 'null' ? null : $this->status_id,
            'brand_id'      => $this->brand_id === 'null' ? null : $this->brand_id,
            'is_featured'   => $this->has('is_featured')
                ? filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'is_active'     => $this->has('is_active')
                ? filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'returnable'    => $this->has('returnable')
                ? filter_var($this->returnable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'is_flash_sale' => $this->has('is_flash_sale')
                ? filter_var($this->is_flash_sale, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
        ]);
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        if (is_object($productId)) {
            $productId = $productId->id;
        }

        return [
            'store_id'              => ['sometimes', 'integer', 'exists:seller_store_settings,id'],
            'category_id'           => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'brand_id'              => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'name'                  => ['sometimes', 'string', 'max:255'],
            'slug'                  => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'price'                 => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'purchase_price'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'list_price'            => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_price'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_percentage'   => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'return_days'           => ['sometimes', 'nullable', 'integer', 'min:0'],
            'returnable'            => ['sometimes', 'nullable', 'boolean'],
            'is_flash_sale'         => ['sometimes', 'nullable', 'boolean'],
            'low_stock_threshold'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tax'                   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock_qty'             => ['sometimes', 'nullable', 'integer', 'min:0'],
            'short_description'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:10000'],
            'product_code'          => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'product_code')->ignore($productId)],
            'model_number'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'manufacturer'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'status_id'             => ['sometimes', 'integer', 'exists:master_types,id'],
            'is_featured'           => ['sometimes', 'boolean'],
            'is_deal'               => ['sometimes', 'nullable', 'boolean'],
            'deal_start_at'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'deal_end_at'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active'             => ['sometimes', 'nullable', 'boolean'],
            'sort_order'            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'published_at'          => ['sometimes', 'nullable', 'date'],

            // Variants - null fully allowed
            'variants'                              => ['sometimes', 'nullable', 'array'],
            'variants.*.id'                         => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.name'                       => ['sometimes', 'nullable', 'string', 'max:255'],
            'variants.*.sku'                        => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (!preg_match('/variants\.(\d+)\.sku/', $attribute, $matches)) {
                        return;
                    }

                    $index     = $matches[1];
                    $variantId = $this->input("variants.{$index}.id");
                    $product   = $this->route('product');
                    $productId = is_object($product) ? $product->id : $product;

                    $query = DB::table('product_variants')->where('sku', $value);

                    if (!empty($variantId)) {
                        $query->where('id', '!=', $variantId);
                    }

                    // Same product ke andar same SKU allow
                    if ($productId) {
                        $sameProductSku = DB::table('product_variants')
                            ->where('product_id', $productId)
                            ->where('sku', $value)
                            ->first();

                        if ($sameProductSku) {
                            return;
                        }
                    }

                    if ($query->exists()) {
                        $fail("The SKU '{$value}' is already taken by another variant.");
                    }
                },
            ],
            'variants.*.barcode'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'variants.*.price'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.stock_qty'                  => ['sometimes', 'nullable', 'integer', 'min:0'], // consistent with DB
            'variants.*.compare_at_price'           => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.cost_price'                 => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.weight'                     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.length'                     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.width'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.height'                     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.manage_stock'               => ['sometimes', 'boolean'],
            'variants.*.is_active'                  => ['sometimes', 'nullable', 'boolean'],
            'variants.*.is_default'                 => ['sometimes', 'nullable', 'boolean'],
            'variants.*.attributes'                 => ['sometimes', 'nullable', 'array'],
            'variants.*.attributes.*.attribute_id'  => ['nullable', 'integer', 'exists:attributes,id'],
            'variants.*.attributes.*.attribute_value_id' => ['nullable', 'integer', 'exists:attribute_values,id'],

            // Images
            'images'                                => ['sometimes', 'nullable', 'array', 'max:10'],
            'images.*'                              => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image_ids'                      => ['sometimes', 'nullable', 'array'],
            'remove_image_ids.*'                    => ['integer', 'nullable', 'exists:product_images,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.exists'                       => 'Selected store does not exist.',
            'category_id.exists'                    => 'Selected category does not exist.',
            'brand_id.exists'                       => 'Selected brand does not exist.',
            'name.max'                              => 'Product name may not exceed 255 characters.',
            'slug.unique'                           => 'This product slug is already in use.',
            'product_code.unique'                   => 'This product code is already in use.',
            'status_id.exists'                      => 'Selected status does not exist.',
            'price.numeric'                         => 'Product price must be a number.',
            'price.min'                             => 'Product price cannot be negative.',
            'discount_percentage.numeric'           => 'Discount percentage must be a number.',
            'discount_percentage.max'               => 'Discount percentage cannot exceed 100.',
            'return_days.integer'                   => 'Return days must be a whole number.',
            'return_days.min'                       => 'Return days cannot be negative.',
            'low_stock_threshold.integer'           => 'Low stock threshold must be a whole number.',

            'variants.array'                        => 'Variants must be provided as an array.',
            'variants.*.id.exists'                  => 'Selected product variant does not exist.',
            'variants.*.price.numeric'              => 'Variant price must be a number.',
            'variants.*.price.min'                  => 'Variant price cannot be negative.',
            'variants.*.sku.max'                    => 'SKU may not exceed 100 characters.',
            'variants.*.barcode.max'                => 'Barcode may not exceed 100 characters.',
            'variants.*.stock_qty.integer'          => 'Variant stock quantity must be a whole number.',
            'variants.*.stock_qty.min'              => 'Variant stock quantity cannot be negative.',
            'variants.*.weight.numeric'             => 'Variant weight must be a number.',
            'variants.*.length.numeric'             => 'Variant length must be a number.',
            'variants.*.width.numeric'              => 'Variant width must be a number.',
            'variants.*.height.numeric'             => 'Variant height must be a number.',
            'variants.*.attributes.array'           => 'Variant attributes must be an array.',
            'variants.*.attributes.*.attribute_id.exists' => 'Selected attribute does not exist.',
            'variants.*.attributes.*.attribute_value_id.exists' => 'Selected attribute value does not exist.',

            'images.array'                          => 'Images must be provided as an array.',
            'images.max'                            => 'You may upload a maximum of 10 new images.',
            'images.*.required'                     => 'Product image is required.',
            'images.*.image'                        => 'Each product image must be a valid image.',
            'images.*.mimes'                        => 'Product images must be JPG, JPEG, PNG, or WEBP.',
            'images.*.max'                          => 'Each product image may not exceed 5 MB.',
            'remove_image_ids.array'                => 'Remove image IDs must be provided as an array.',
            'remove_image_ids.*.exists'             => 'Selected product image does not exist.',
        ];
    }
}
