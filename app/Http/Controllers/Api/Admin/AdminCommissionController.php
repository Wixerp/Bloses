<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminCommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCommissionController extends Controller
{
    public function __construct(
        private AdminCommissionService $commissionService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission settings fetched successfully.',
            'data' => $this->commissionService->index($filters),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'name' => ['required', 'string', 'max:100'],
            'commission_type' => [
                'required',
                Rule::in(['percentage', 'fixed']),
            ],
            'commission_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (
            $data['commission_type'] === 'percentage'
            && $data['commission_rate'] > 100
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage commission 100 se zyada nahi ho sakta.',
                'errors' => [
                    'commission_rate' => [
                        'Percentage commission 0 se 100 ke darmiyan hona chahiye.',
                    ],
                ],
            ], 422);
        }

        $setting = $this->commissionService->store(
            $data,
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Commission setting created successfully.',
            'data' => $setting,
        ], 201);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Commission setting fetched successfully.',
            'data' => $this->commissionService->show($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'seller_id' => ['sometimes', 'nullable', 'integer', 'exists:sellers,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'commission_type' => [
                'sometimes',
                Rule::in(['percentage', 'fixed']),
            ],
            'commission_rate' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:1000000000',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $current = $this->commissionService->show($id);
        $type = $data['commission_type'] ?? $current->commission_type;
        $rate = $data['commission_rate'] ?? $current->commission_rate;

        if ($type === 'percentage' && $rate > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage commission 100 se zyada nahi ho sakta.',
                'errors' => [
                    'commission_rate' => [
                        'Percentage commission 0 se 100 ke darmiyan hona chahiye.',
                    ],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commission setting updated successfully.',
            'data' => $this->commissionService->update($id, $data),
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission status updated successfully.',
            'data' => $this->commissionService->updateStatus(
                $id,
                (bool) $data['is_active']
            ),
        ]);
    }

    public function history(Request $request)
    {
        $filters = $request->validate([
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'status' => [
                'nullable',
                Rule::in(['pending', 'applied', 'reversed']),
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission history fetched successfully.',
            'data' => $this->commissionService->history($filters),
        ]);
    }
}
