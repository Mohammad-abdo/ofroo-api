<?php

namespace App\Support;

/**
 * Laravel's `image` rule is strict and often returns "must be an image" for valid files
 * (HEIC, some WebP/AVIF, BMP, TIFF, etc.). Prefer `file` + explicit mimes.
 */
final class ImageUploadRules
{
    /** Raster, vector, and common phone / modern formats */
    public const MIMES = 'jpeg,jpg,pjpeg,png,gif,webp,bmp,svg,ico,tiff,tif,avif,heic,heif';

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
