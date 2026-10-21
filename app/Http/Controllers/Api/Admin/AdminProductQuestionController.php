<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminProductQuestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AdminProductQuestionController extends Controller
{
    public function __construct(
        private AdminProductQuestionService $questionService
    ) {}

    /**
     * GET /api/admin/buyer-seller-questions
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'is_approved' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'answer_status' => [
                'nullable',
                Rule::in(['answered', 'unanswered']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->questionService->index($filters);

        return response()->json([
            'success' => true,
            'message' => 'Buyer-seller questions retrieved successfully.',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * GET /api/admin/buyer-seller-questions/{id}
     */
    public function show(int $id): JsonResponse
    {
        $question = $this->questionService->show($id);

        return response()->json([
            'success' => true,
            'message' => 'Question details retrieved successfully.',
            'data' => $question,
        ]);
    }

    /**
     * PATCH /api/admin/buyer-seller-questions/{id}/approval
     */
    public function updateApproval(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $question = $this->questionService->updateApproval(
            $id,
            $request->boolean('is_approved')
        );

        return response()->json([
            'success' => true,
            'message' => $question->is_approved
                ? 'Question approved successfully.'
                : 'Question rejected successfully.',
            'data' => $question,
        ]);
    }
}
