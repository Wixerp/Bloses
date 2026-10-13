<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Services\Suppliers\SuppliersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuppliersController extends Controller
{
    public function __construct(
        private SuppliersService $suppliersService
    ) {}

    // GET: /api/admin/suppliers
    public function index(Request $request): JsonResponse
    {
        $suppliers = $this->suppliersService->getAll(
            $request->only(['search', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Suppliers fetched successfully.',
            'data' => $suppliers,
        ]);
    }

    // GET: /api/admin/suppliers/{id}
    public function show(int $id): JsonResponse
    {
        $supplier = $this->suppliersService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Supplier fetched successfully.',
            'data' => $supplier,
        ]);
    }

    // POST: /api/admin/suppliers
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
        ]);

        $supplier = $this->suppliersService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successfully.',
            'data' => $supplier,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // Ensure supplier exists
        $this->suppliersService->getById($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $supplier = $this->suppliersService->update($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully.',
            'data' => $supplier,
        ]);
    }

    // DELETE: /api/admin/suppliers/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->suppliersService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully.',
        ]);
    }
}
