<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminRefundService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRefundController extends Controller
{
    public function __construct(
        private AdminRefundService $refundService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer', 'exists:master_types,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refunds fetched successfully.',
            'data' => $this->refundService->index($filters),
        ]);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Refund details fetched successfully.',
            'data' => $this->refundService->show($id),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate([
            'status_id' => ['required', 'integer', 'exists:master_types,id'],
            'approved_amount' => ['required', 'numeric', 'gt:0'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $refund = $this->refundService->approve(
            $id,
            (int) $data['status_id'],
            (float) $data['approved_amount'],
            $data['admin_note'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund approved successfully.',
            'data' => $refund,
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'status_id' => ['required', 'integer', 'exists:master_types,id'],
            'admin_note' => ['required', 'string', 'max:5000'],
        ]);

        $refund = $this->refundService->reject(
            $id,
            (int) $data['status_id'],
            $data['admin_note']
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund rejected successfully.',
            'data' => $refund,
        ]);
    }

    public function markRefunded(Request $request, int $id)
    {
        $data = $request->validate([
            'status_id' => ['required', 'integer', 'exists:master_types,id'],
            'refunded_amount' => ['required', 'numeric', 'gt:0'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'gateway' => ['nullable', 'string', 'max:255'],
            'gateway_response' => ['nullable', 'string', 'max:10000'],
        ]);

        $refund = $this->refundService->markRefunded(
            $id,
            (int) $data['status_id'],
            (float) $data['refunded_amount'],
            $data['transaction_id'] ?? null,
            $data['gateway'] ?? null,
            $data['gateway_response'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund marked as completed successfully.',
            'data' => $refund,
        ]);
    }

    public function summary()
    {
        return response()->json([
            'success' => true,
            'message' => 'Refund summary fetched successfully.',
            'data' => $this->refundService->summary(),
        ]);
    }
}
