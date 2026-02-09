<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StorageHelper
{
    /**
     * Upload file to public storage
     * 
     * @param UploadedFile $file
     * @param string $directory
     * @param string|null $filename
     * @return array ['path' => string, 'url' => string]
     */
    public static function uploadFile(UploadedFile $file, string $directory, ?string $filename = null): array
    {
        try {
            // Ensure directory exists
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory, 0755, true);
            }

            // Generate unique filename if not provided
            if (!$filename) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            }

            // Store file
            $filePath = $file->storeAs($directory, $filename, 'public');

            if (!$filePath) {
                throw new \Exception('Failed to store file');
            }

            // Verify file exists
            if (!Storage::disk('public')->exists($filePath)) {
                throw new \Exception('File was not stored correctly');
            }

            // Generate URL - ensure it doesn't include /api in the path
            // Get base URL from request (if available) or config
            $baseUrl = null;
            if (app()->runningInConsole()) {
                // In console, use config URL and remove /api
                $baseUrl = config('app.url', 'http://localhost');
            } else {
                // In web context, use request URL
                $baseUrl = request()->getSchemeAndHttpHost() ?? config('app.url', url('/'));
            }
            
            // Remove /api from base URL if it exists
            $baseUrl = rtrim(str_replace('/api', '', $baseUrl), '/');
            
            // Build file URL: base_url/storage/file_path
            $fileUrl = $baseUrl . '/storage/' . $filePath;

            Log::info('File uploaded successfully', [
                'directory' => $directory,
                'filename' => $filename,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            return [
                'path' => $filePath,
                'url' => $fileUrl,
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            Log::error('Error uploading file', [
                'directory' => $directory,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete file from public storage
     * 
     * @param string $filePathOrUrl
     * @return bool
     */
    public static function deleteFile(string $filePathOrUrl): bool
    {
        try {
            // Convert URL to path if needed
            $filePath = self::urlToPath($filePathOrUrl);
            
            if (!$filePath) {
                Log::warning('Could not convert URL to path', ['url' => $filePathOrUrl]);
                return false;
            }

            if (Storage::disk('public')->exists($filePath)) {
                $deleted = Storage::disk('public')->delete($filePath);
                Log::info('File deleted', [
                    'file_path' => $filePath,
                    'deleted' => $deleted,
                ]);
                return $deleted;
            }

            Log::warning('File does not exist', ['file_path' => $filePath]);
            return false;
        } catch (\Exception $e) {
            Log::error('Error deleting file', [
                'file_path' => $filePathOrUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Convert storage URL to file path
     * 
     * @param string $url
     * @return string|null
     */
    public static function urlToPath(string $url): ?string
    {
        // Remove base URL
        $path = str_replace(url('/storage/'), '', $url);
        $path = str_replace(url('/'), '', $path);
        
        // Remove 'storage/' prefix if exists
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }
        
        // Remove leading slash
        $path = ltrim($path, '/');
        
        return $path ?: null;
    }

    /**
     * Upload merchant logo
     * 
     * @param UploadedFile $file
     * @param int $merchantId
     * @return array ['path' => string, 'url' => string]
     */
    public static function uploadMerchantLogo(UploadedFile $file, int $merchantId): array
    {
        $directory = 'merchants/logos';
        $filename = time() . '_' . $merchantId . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        return self::uploadFile($file, $directory, $filename);
    }

    /**
     * Upload user avatar
     * 
     * @param UploadedFile $file
     * @param int $userId
     * @return array ['path' => string, 'url' => string]
     */
    public static function uploadUserAvatar(UploadedFile $file, int $userId): array
    {
        $directory = 'users/avatars';
        $filename = time() . '_' . $userId . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        return self::uploadFile($file, $directory, $filename);
    }

    /**
     * Upload offer image
     * 
     * @param UploadedFile $file
     * @return array ['path' => string, 'url' => string]
     */
    public static function uploadOfferImage(UploadedFile $file): array
    {
        $directory = 'offers';
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        return self::uploadFile($file, $directory, $filename);
    }

    /**
     * Upload mall image
     * 
     * @param UploadedFile $file
     * @return array ['path' => string, 'url' => string]
     */
    public static function uploadMallImage(UploadedFile $file): array
    {
        $directory = 'malls';
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        return self::uploadFile($file, $directory, $filename);
    }

    /**
     * Ensure directory exists
     * 
     * @param string $directory
     * @return bool
     */
    public static function ensureDirectoryExists(string $directory): bool
    {
        try {
            if (!Storage::disk('public')->exists($directory)) {
                return Storage::disk('public')->makeDirectory($directory, 0755, true);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Error creating directory', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate image file
     * 
     * @param UploadedFile $file
     * @param int $maxSizeMB
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateImage(UploadedFile $file, int $maxSizeMB = 2): array
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $maxSize = $maxSizeMB * 1024 * 1024;

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return [
                'valid' => false,
                'error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedMimes),
            ];
        }

        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'error' => "File size exceeds maximum allowed size of {$maxSizeMB}MB",
            ];
        }

        if (!$file->isValid()) {
            return [
                'valid' => false,
                'error' => 'File upload failed or file is corrupted',
            ];
        }

        return ['valid' => true, 'error' => null];
    }
}

