<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminProductApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class AdminProductApprovalController extends Controller
{
    public function __construct(
        private AdminProductApprovalService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pending product approvals fetched successfully.',
            'data' => $this->service->index($filters),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product details fetched successfully.',
            'data' => $this->service->show($id),
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $result = $this->service->approve($id, (int) auth()->id());

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $result = $this->service->reject(
            $id,
            (int) auth()->id(),
            $validated['admin_note']
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }
}
