<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminTransactionService;
use Illuminate\Http\Request;

class AdminTransactionController extends Controller
{
    public function __construct(
        protected AdminTransactionService $transactionService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_method_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = $this->transactionService->index($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transactions fetched successfully.',
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function summary()
    {
        return response()->json([
            'success' => true,
            'message' => 'Transaction summary fetched successfully.',
            'data' => $this->transactionService->summary(),
        ]);
    }
}
