<?php

namespace App\Http\Controllers\API\Buyer;

use App\Http\Controllers\Controller;
use App\Services\Buyer\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    public function index(): JsonResponse
    {
        $addresses = $this->addressService->getAddresses();

        return response()->json([
            'success' => true,
            'message' => 'Addresses retrieved successfully.',
            'data' => $addresses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address_type_id' => ['required','integer','exists:master_types,id',],
            'label' => ['nullable','string','max:255',],
            'first_name' => ['required','string','max:255',],
            'last_name' => ['nullable','string','max:255',],
            'email' => ['nullable','string','max:255',],
            'phone' => ['required','string','max:50',],
            'address_line_1' => ['required','string',],
            'address_line_2' => ['nullable','string',],
            'country_id' => ['required','integer',],
            'state_id' => ['nullable','integer',],
            'city_id' => ['nullable','integer',],
            'postal_code' => ['nullable','string','max:50',],
            'latitude' => ['nullable','numeric',],
            'longitude' => ['nullable','numeric',],
            'is_default' => ['nullable','boolean',],
        ]);

        $address = $this->addressService->createAddress($data);

        return response()->json([
            'success' => true,
            'message' => 'Address added successfully.',
            'data' => $address,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'address_type_id' => ['required','integer','exists:master_types,id',],
            'label' => ['nullable','string','max:255',],
            'first_name' => ['required','string','max:255',],
            'last_name' => ['nullable','string','max:255',],
            'email' => ['nullable','string','max:255',],
            'phone' => ['required','string','max:50',],
            'address_line_1' => ['required','string',],
            'address_line_2' => ['nullable','string',],
            'country_id' => ['required','integer',],
            'state_id' => ['nullable','integer',],
            'city_id' => ['nullable','integer',],
            'postal_code' => ['nullable','string','max:50',],
            'latitude' => ['nullable','numeric',],
            'longitude' => ['nullable','numeric',],
            'is_default' => ['nullable','boolean',],
        ]);

        $address = $this->addressService->updateAddress($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => $address,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->addressService->deleteAddress($id);

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }
}
