<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\BusinessVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BusinessVerificationController extends Controller
{
    public function __construct(
        private BusinessVerificationService $service
    ) {}

    private function errorResponse(\Throwable $e)
    {
        if ($e instanceof ValidationException) {
            throw $e;
        }

        Log::error('Seller verification API error', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to process verification request.',
        ], 500);
    }

    public function show()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Verification details retrieved successfully.',
                'data' => $this->service->getVerification(),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateBusiness(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'sometimes|required|string|max:255',
            'business_email' => 'sometimes|nullable|email|max:255',
            'business_phone' => 'sometimes|nullable|string|max:30',
            'description' => 'sometimes|nullable|string|max:5000',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Business details updated successfully.',
                'data' => $this->service->updateBusiness($data),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function addresses()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->getAddresses(),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function saveAddress(Request $request)
    {
        $data = $request->validate([
            'state_id' => 'nullable|integer|exists:states,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:30',
            'country' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'is_primary' => 'sometimes|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Address saved successfully.',
                'data' => $this->service->saveAddress($data),
            ], 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateAddress(Request $request, int $id)
    {
        $data = $request->validate([
            'state_id' => 'sometimes|nullable|integer|exists:states,id',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'sometimes|nullable|string|max:255',
            'area' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:30',
            'country' => 'sometimes|required|string|max:100',
            'contact_person' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'is_primary' => 'sometimes|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully.',
                'data' => $this->service->saveAddress($data, $id),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function bankAccounts()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->getBankAccounts(),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function saveBankAccount(Request $request)
    {
        $data = $request->validate([
            'account_type_id' => 'nullable|integer|exists:master_types,id',
            'account_holder_name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:100',
            'branch_name' => 'nullable|string|max:255',
            'branch_code' => 'nullable|string|max:50',
            'mobile_number' => 'nullable|string|max:30',
            'is_primary' => 'sometimes|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Bank account saved. Verification is pending.',
                'data' => $this->service->saveBankAccount($data),
            ], 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function uploadDocument(Request $request)
    {
        $data = $request->validate([
            'document_type_id' => 'required|integer|exists:master_types,id',
            'document_number' => 'nullable|string|max:100',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $document = $this->service->uploadDocument(
                $data,
                $request->file('document')
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'data' => $document,
            ], 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function deleteDocument(int $id)
    {
        try {
            $this->service->deleteDocument($id);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
}
