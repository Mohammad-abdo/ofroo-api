<?php

namespace App\Support;

/**
 * Build absolute public URLs for media paths returned to mobile clients.
 * Ensures JSON responses use strings (never booleans) for image fields.
 */
final class ApiMediaUrl
{
    /**
     * Single image path or URL → absolute URL string (empty string if missing).
     */
    public static function publicAbsolute(?string $path): string
    {
        if ($path === null) {
            return '';
        }
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $path = ltrim($path, '/');
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'storage/')) {
            return $base . '/' . $path;
        }

        return $base . '/storage/' . $path;
    }

    /**
     * Offer gallery / image lists: only string paths/URLs; drops invalid entries (e.g. booleans from bad client payloads).
     *
     * @return list<string>
     */
    public static function absoluteList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $abs = self::publicAbsolute($entry);
            if ($abs !== '') {
                $out[] = $abs;
            }
        }

        return array_values($out);
    }

    /**
     * Same as {@see publicAbsolute()} but null when there is no usable URL.
     */
    public static function publicAbsoluteOrNull(?string $path): ?string
    {
        $s = self::publicAbsolute($path);

        return $s !== '' ? $s : null;
    }
}
