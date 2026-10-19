<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSellerDocumentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function view(
        int $documentId
    ): BinaryFileResponse {
        return $this->documentService
            ->viewDocument($documentId);
    }

    /**
     * Download document.
     */
    public function download(
        int $documentId
    ): StreamedResponse {
        return $this->documentService
            ->downloadDocument($documentId);
    }
}
