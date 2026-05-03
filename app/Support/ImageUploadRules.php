<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Laravel's `image` rule is strict and often returns "must be an image" for valid files
 * (HEIC, some WebP/AVIF, BMP, TIFF, etc.). Prefer `file` + explicit mimes.
 */
final class ImageUploadRules
{
    /** Raster, vector, and common phone / modern formats */
    public const MIMES = 'jpeg,jpg,jpe,jfif,pjpeg,png,gif,webp,bmp,svg,ico,tiff,tif,avif,heic,heif';

    /**
     * Loose image check: max size + mime sniffing (and common iOS HEIC as octet-stream).
     * Use for admin mall/hero uploads where strict `mimes:` rejects valid cameras.
     *
     * @return list<string|Closure>
     */
    public static function permissiveImageMax(int $maxKb): array
    {
        return [
            'required',
            'file',
            'max:'.$maxKb,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    $fail('The image field must be a file.');

                    return;
                }
                if (! $value->isValid()) {
                    $fail($value->getErrorMessage() ?: 'Invalid upload.');

                    return;
                }
                $path = $value->getRealPath() ?: $value->getPathname();
                if (! is_string($path) || $path === '' || ! is_readable($path)) {
                    $fail('Could not read the uploaded file.');

                    return;
                }
                $mime = @mime_content_type($path) ?: (string) $value->getMimeType();
                $ext = strtolower((string) $value->getClientOriginalExtension());
                $extFromName = strtolower(pathinfo((string) $value->getClientOriginalName(), PATHINFO_EXTENSION));
                $ext = $ext !== '' ? $ext : $extFromName;

                $looksImageMime = $mime !== '' && str_starts_with($mime, 'image/');
                $looksImageOctet = $mime === 'application/octet-stream'
                    && in_array($ext, ['jpg', 'jpeg', 'jpe', 'jfif', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'avif', 'ico', 'svg'], true);

                if (! $looksImageMime && ! $looksImageOctet) {
                    $fail('The file must be a valid image (any common format).');
                }
            },
        ];
    }

    public static function fileMax(int $maxKb): string
    {
        return 'file|mimes:'.self::MIMES.'|max:'.$maxKb;
    }

    public static function nullableFileMax(int $maxKb): string
    {
        return 'nullable|file|mimes:'.self::MIMES.'|max:'.$maxKb;
    }

    public static function requiredFileMax(int $maxKb): string
    {
        return 'required|file|mimes:'.self::MIMES.'|max:'.$maxKb;
    }

    public static function sometimesFileMax(int $maxKb): string
    {
        return 'sometimes|file|mimes:'.self::MIMES.'|max:'.$maxKb;
    }
}
