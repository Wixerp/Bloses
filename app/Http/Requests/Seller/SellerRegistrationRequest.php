<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class SellerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'cnic_number' => 'required|string|max:50',
            'dob' => 'required|date',
            'gender' => 'required|string|max:50',
            'mobile' => 'nullable|string|max:30',
            'email' => 'required|email|max:255',
            'country' => 'required|string|max:255',
            'province' => 'required',
            'city' => 'required',
            'area' => 'nullable|string|max:255',
            'address' => 'required|string',
            'postal_code' => 'nullable|string|max:50',
            'cnic_front' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'cnic_back' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'account_type_id' => 'nullable|exists:master_types,id',
            "account_holder" => "required|string|max:255",
            "bank_name" => "required|string|max:255",
            "account_number" => "required|string|max:255",
            "iban" => "required|string|max:255",
            "branch_name" => "required|string|max:255",
            "branch_code" => "required|string|max:255",
            "mobile" => "required|string|max:255",
            'seller_type' => 'required|string|max:100',
            'business_name' => 'required|string|max:255',
            'business_description' => 'nullable|string',
            'business_type' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:255',
            'ntn' => 'nullable|string|max:255',
            'sales_tax_number' => 'nullable|string|max:255',
            'business_address' => 'nullable|string',
            'business_phone' => 'nullable|string|max:30',
            'business_email' => 'nullable|email|max:255',
            'store_name' => 'required|string|max:255',
            'store_description' => 'nullable|string',
            'store_logo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'store_banner' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'terms_agreed' => 'required|boolean',
            'commission_agreed' => 'required|boolean',
            'return_policy_agreed' => 'required|boolean',
            'info_confirmed' => 'required|boolean',
        ];
    }
}
