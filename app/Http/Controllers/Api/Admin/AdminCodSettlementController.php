<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminCodSettlementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCodSettlementController extends Controller
{
    public function __construct(
        private AdminCodSettlementService $service
    ) {
    }

    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->summary(),
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => [
                'nullable',
                Rule::in(['pending', 'received', 'disputed', 'cancelled']),
            ],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'carrier' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            ...$this->service->index($filters),
        ]);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->show($id),
        ]);
    }

    public function updateRemittance(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['pending', 'received', 'disputed', 'cancelled']),
            ],
            'collected_amount' => ['sometimes', 'numeric', 'min:0'],
            'remitted_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'courier_reference' => ['nullable', 'string', 'max:150'],
            'received_reference' => ['nullable', 'string', 'max:150'],
            'collected_at' => ['nullable', 'date'],
            'remitted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->updateRemittance($id, $data),
        ]);
    }
}
