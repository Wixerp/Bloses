<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    // public function authorize(): bool
    // {
    //     return auth()->check();
    // }
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255',],
            'rating' => ['required','numeric','min:1','max:5',],
            'review' => ['required','string','max:5000',],
            'product_variant_id' => ['nullable','integer','exists:product_variants,id',],
            'images' => ['nullable','array','max:5',],
            'images.*' => ['image','mimes:jpg,jpeg,png,webp','max:5120',],
        ];
    }
}
