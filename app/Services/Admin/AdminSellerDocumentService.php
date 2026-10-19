<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSellerDocumentService
{
    /**
     * Get seller details and uploaded documents.
     */
    public function getSellerDocuments(int $sellerId): array
    {
        $seller = DB::table('sellers')
            ->join('users', 'users.id', '=', 'sellers.user_id')
            ->where('sellers.id', $sellerId)
            ->select(
                'sellers.id',
                'sellers.user_id',
                'sellers.business_name',
                'users.name',
                'users.email'
            )
            ->first();

        if (!$seller) {
            abort(404, 'Seller not found.');
        }

        $documents = DB::table('seller_documents')
            ->leftJoin(
                'master_types as doc_type',
                'doc_type.id',
                '=',
                'seller_documents.document_type_id'
            )
            ->leftJoin(
                'master_types as doc_status',
                'doc_status.id',
                '=',
                'seller_documents.status_id'
            )
            ->where('seller_documents.seller_id', $sellerId)
            ->select(
                'seller_documents.id',
                'seller_documents.seller_id',
                'seller_documents.document_type_id',
                'doc_type.description as document_type',
                'seller_documents.document_number',
                'seller_documents.original_file_name',
                'seller_documents.mime_type',
                'seller_documents.file_size',
                'seller_documents.status_id',
                'doc_status.description as status',
                'seller_documents.admin_note',
                'seller_documents.verified_at',
                'seller_documents.verified_by',
                'seller_documents.created_at'
            )
            ->orderByDesc('seller_documents.id')
            ->get()
            ->map(function ($document) {
                $document->view_url = url(
                    "/api/admin/seller-documents/{$document->id}/view"
                );

                $document->download_url = url(
                    "/api/admin/seller-documents/{$document->id}/download"
                );

                return $document;
            });

        return [
            'seller' => $seller,
            'total_documents' => $documents->count(),
            'data' => $documents,
        ];
    }

    /**
     * Find a document and verify that its file exists.
     */
    private function getDocument(int $documentId): object
    {
        $document = DB::table('seller_documents')
            ->where('id', $documentId)
            ->first();

        if (!$document) {
            abort(404, 'Document not found.');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Document file not found in storage.');
        }

        return $document;
    }

    /**
     * View an image or PDF in the browser.
     */
    public function viewDocument(
        int $documentId
    ): BinaryFileResponse {
        $document = $this->getDocument($documentId);

        $disk = Storage::disk('public');

        $mimeType = $disk->mimeType($document->file_path)
            ?: 'application/octet-stream';

        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ];

        if (!in_array($mimeType, $allowedTypes, true)) {
            abort(415, 'This file type cannot be previewed.');
        }

        return response()->file(
            $disk->path($document->file_path),
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => 'inline; filename="'
                    . basename($document->original_file_name)
                    . '"',
            ]
        );
    }

    /**
     * Download a seller document.
     */
    public function downloadDocument(
        int $documentId
    ): StreamedResponse {
        $document = $this->getDocument($documentId);

        return Storage::disk('public')->download(
            $document->file_path,
            basename($document->original_file_name),
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
