<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPayoutController extends Controller
{
    public function __construct(
        private AdminPayoutService $payoutService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'status' => [
                'nullable',
                'in:pending,approved,processing,paid,rejected,failed',
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout requests fetched successfully.',
            'data' => $this->payoutService->listing($filters),
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payout summary fetched successfully.',
            'data' => $this->payoutService->summary(),
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payout approved successfully.',
            'data' => $this->payoutService->approve($id),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout rejected successfully.',
            'data' => $this->payoutService->reject(
                $id,
                $data['reason']
            ),
        ]);
    }

    public function processing(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'payment_gateway' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout marked as processing.',
            'data' => $this->payoutService->markProcessing($id, $data),
        ]);
    }

    public function paid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
            'payment_gateway' => ['nullable', 'string', 'max:100'],
            'gateway_response' => ['nullable', 'string', 'max:10000'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout marked as paid successfully.',
            'data' => $this->payoutService->markPaid($id, $data),
        ]);
    }


    public function payoutOptions()
    {
        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found.',
            ], 404);
        }

        $accounts = DB::table('seller_bank_accounts')
            ->where('seller_id', $sellerId)->where('is_verified', true)
            ->select([
                'id as seller_bank_account_id',
                'bank_name',
                'account_holder_name',
                'is_primary',
            ])->orderByDesc('is_primary')->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }
}
