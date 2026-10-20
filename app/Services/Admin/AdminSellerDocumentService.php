<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\{DB, Storage};
use Symfony\Component\HttpFoundation\{BinaryFileResponse, StreamedResponse};

class AdminSellerDocumentService
{
    public function getSellerDocuments(int $sellerId): array {
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

    private function getDocument(int $documentId): object
    {
        $document = DB::table('seller_documents')->where('id', $documentId)->first();

        if (!$document) {
            abort(404, 'Seller document not found.');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Document file not found in storage.');
        }

        return $document;
    }

    public function viewDocument(int $documentId): BinaryFileResponse {
        $document = $this->getDocument($documentId);
        $disk = Storage::disk('public');

        $mimeType = $disk->mimeType($document->file_path) ?: 'application/octet-stream';

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
                'Content-Disposition' => 'inline; filename="' . basename($document->original_file_name) . '"',
            ]
        );
    }

    public function downloadDocument(int $documentId): StreamedResponse {
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

    public function updateDocumentStatus(int $documentId, string $action, ?string $adminNote = null): array {
        if (!in_array($action, ['approved', 'disapproved'], true)) {
            abort(422, 'Action must be approved or disapproved.');
        }

        if ($action === 'disapproved' && blank($adminNote)) {
            abort(422, 'Disapproval reason is required.');
        }

        $statusId = match ($action) {
            'approved' => 36,
            'disapproved' => 37,
        };

        $adminId = auth()->id();

        if (!$adminId) {
            abort(401, 'Admin authentication required.');
        }

        return DB::transaction(function () use ($documentId, $action, $adminNote, $statusId, $adminId) {
            $document = DB::table('seller_documents')->where('id', $documentId)->lockForUpdate()->first();

            if (!$document) {
                abort(404, 'Seller document not found.');
            }

            DB::table('seller_documents')->where('id', $documentId)
                ->update([
                    'status_id' => $statusId,
                    'admin_note' => $action === 'disapproved' ? trim($adminNote ?? '') : null,
                    'verified_at' => now(),
                    'verified_by' => $adminId,
                    'updated_at' => now(),
                ]);

            $updated = DB::table('seller_documents')->where('id', $documentId)->first();

            if ((int) $updated->status_id !== $statusId) {
                throw new \RuntimeException('Document status was not saved correctly.');
            }

            return [
                'id' => $updated->id,
                'seller_id' => $updated->seller_id,
                'document_type_id' => $updated->document_type_id,
                'status_id' => (int) $updated->status_id,
                'status' => $statusId === 36 ? 'Approved' : 'Disapproved',
                'admin_note' => $updated->admin_note,
                'verified_at' => $updated->verified_at,
                'verified_by' => $updated->verified_by,
                'updated_at' => $updated->updated_at,
            ];
        });
    }

}
