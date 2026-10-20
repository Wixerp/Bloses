<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSellerDocumentService;
use Illuminate\Http\{Request, JsonResponse};
use Symfony\Component\HttpFoundation\{BinaryFileResponse, StreamedResponse};

class AdminSellerDocumentController extends Controller
{
    protected AdminSellerDocumentService $documentService;

    public function __construct(
        AdminSellerDocumentService $documentService
    ) {
        $this->documentService = $documentService;
    }

    /**
     * List seller documents.
     */
    public function index(int $sellerId): JsonResponse
    {
        $result = $this->documentService
            ->getSellerDocuments($sellerId);

        return response()->json([
            'success' => true,
            'message' => 'Seller documents retrieved successfully.',
            'seller' => $result['seller'],
            'total_documents' => $result['total_documents'],
            'data' => $result['data'],
        ]);
    }

    /**
     * View document.
     */
    public function view(int $documentId): BinaryFileResponse
    {
        return $this->documentService
            ->viewDocument($documentId);
    }

    /**
     * Download document.
     */
    public function download(int $documentId): StreamedResponse
    {
        return $this->documentService
            ->downloadDocument($documentId);
    }

    /**
     * Update document approval status.
     */
    public function updateStatus(
        Request $request,
        int $documentId
    ): JsonResponse {
        $data = $request->validate([
            'action' => 'required|in:approved,disapproved',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        if (
            $data['action'] === 'disapproved'
            && blank($data['admin_note'] ?? null)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Disapproval reason is required.',
            ], 422);
        }

        $result = $this->documentService->updateDocumentStatus(
            $documentId,
            $data['action'],
            $data['admin_note'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => $result['status'] === 'Approved'
                ? 'Document approved successfully.'
                : 'Document disapproved successfully.',
            'data' => $result,
        ]);
    }
}
