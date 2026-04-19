<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Throwable;

/**
 * Thin wrapper around endroid/qr-code v6 so callers never touch the
 * vendor API directly. Keeps every response JSON-safe (strings only)
 * and falls back to a harmless empty string on error.
 */
class QrCodeService
{
    /**
     * Generate a QR code for the payload and return a "data:image/png;base64,..." URI
     * suitable for direct use in an <img src="..."> or Flutter's Image.memory().
     */
    public function dataUri(string $payload, int $size = 320): string
    {
        if (trim($payload) === '') {
            return '';
        }

        try {
            $qr = new QrCode(
                data: $payload,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            );

            $result = (new PngWriter())->write($qr);

            return (string) $result->getDataUri();
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Same as {@see dataUri()} but returns only the raw base64 string (no data: prefix).
     */
    public function base64(string $payload, int $size = 320): string
    {
        $uri = $this->dataUri($payload, $size);
        if ($uri === '') {
            return '';
        }
        $comma = strpos($uri, ',');

        return $comma === false ? '' : substr($uri, $comma + 1);
    }
}
