<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key'    => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
        ]);
    }

    /**
     * Upload image/file to Cloudinary
     */
    public function upload(
        UploadedFile $file,
        string $folder = 'marketplace'
    ): array {
        $result = $this->cloudinary
            ->uploadApi()
            ->upload(
                $file->getRealPath(),
                [
                    'folder' => $folder,
                    'resource_type' => 'auto',
                ]
            );

        return [
            'url' => $result['secure_url'] ?? null,
            'public_id' => $result['public_id'] ?? null,
        ];
    }

    /**
     * Delete file from Cloudinary
     */
    public function delete(?string $publicId): void
    {
        if (!$publicId) {
            return;
        }

        $this->cloudinary
            ->uploadApi()
            ->destroy($publicId);
    }
}
