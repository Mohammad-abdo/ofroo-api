<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * Upload offer images
     */
    public function uploadOfferImages(array $images): array
    {
        $uploadedImages = [];

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $path = $image->store('offers', 'public');
                $uploadedImages[] = Storage::url($path);
            } elseif (is_string($image)) {
                // If it's already a URL or base64, handle accordingly
                $uploadedImages[] = $image;
            }
        }

        return $uploadedImages;
    }

    /**
     * Delete offer images
     */
    public function deleteOfferImages(array $imagePaths): void
    {
        foreach ($imagePaths as $path) {
            if (str_starts_with($path, '/storage/')) {
                $filePath = str_replace('/storage/', '', $path);
                Storage::disk('public')->delete($filePath);
            }
        }
    }

    /**
     * Validate image
     */
    public function validateImage(UploadedFile $image): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        return in_array($image->getMimeType(), $allowedMimes) 
            && $image->getSize() <= $maxSize;
    }
}


