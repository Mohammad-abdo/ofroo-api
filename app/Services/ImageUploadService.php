<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    protected int $maxSizeKb;
    protected array $allowedMimes;
    protected string $disk;

    public function __construct()
    {
        $this->maxSizeKb = (int) config('app.max_admin_image_upload_kb', 131072);
        $this->allowedMimes = ['jpeg', 'png', 'jpg', 'gif', 'webp'];
        $this->disk = 'public';
    }

    public function uploadSingle(Request $request, string $key, string $folder = 'uploads'): ?string
    {
        if (!$request->hasFile($key)) {
            $url = $request->input($key);
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            return null;
        }

        $file = $request->file($key);
        if (!$file || !$file->isValid()) {
            return null;
        }

        return $this->store($file, $folder);
    }

    public function uploadMultiple(Request $request, string $key, string $folder = 'uploads'): array
    {
        $urls = [];

        if ($request->hasFile($key)) {
            $files = $request->file($key);
            $files = is_array($files) ? $files : [$files];

            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $url = $this->store($file, $folder);
                    if ($url) {
                        $urls[] = $url;
                    }
                }
            }
        }

        $inputUrls = $request->input($key, []);
        if (is_string($inputUrls)) {
            $inputUrls = json_decode($inputUrls, true) ?? [];
        }

        foreach ($inputUrls as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    public function store($file, string $folder = 'uploads'): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $this->allowedMimes)) {
            return null;
        }

        $maxBytes = $this->maxSizeKb * 1024;
        if ($file->getSize() > $maxBytes) {
            return null;
        }

        $filename = time() . '_' . uniqid() . '.' . $extension;
        $path = $file->storeAs($folder, $filename, $this->disk);

        return asset('storage/' . $path);
    }

    public function delete(string $path): bool
    {
        if (str_starts_with($path, 'http')) {
            $path = str_replace(asset('storage/'), '', $path);
        }

        if ($path && !str_starts_with($path, 'http')) {
            return Storage::disk($this->disk)->delete($path);
        }

        return false;
    }

    public function deleteMultiple(array $paths): int
    {
        $deleted = 0;
        foreach ($paths as $path) {
            if ($this->delete($path)) {
                $deleted++;
            }
        }
        return $deleted;
    }
}
