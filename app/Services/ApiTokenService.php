<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;

final class ApiTokenService
{
    public const NAME_ACCESS = 'access';

    public const NAME_REFRESH = 'refresh';

    public static function accessTtlMinutes(): int
    {
        return max(1, (int) Config::get('sanctum_tokens.access_expires_minutes', 60));
    }

    public static function refreshTtlDays(): int
    {
        return max(1, (int) Config::get('sanctum_tokens.refresh_expires_days', 30));
    }

    public static function accessExpiresAt(): CarbonInterface
    {
        return now()->addMinutes(self::accessTtlMinutes());
    }

    public static function refreshExpiresAt(): CarbonInterface
    {
        return now()->addDays(self::refreshTtlDays());
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, access_expires_at: string, refresh_expires_at: string}
     */
    public static function issuePair(User $user): array
    {
        $accessExpires = self::accessExpiresAt();
        $refreshExpires = self::refreshExpiresAt();

        $access = $user->createToken(self::NAME_ACCESS, ['*'], $accessExpires);
        $refresh = $user->createToken(self::NAME_REFRESH, ['refresh'], $refreshExpires);

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'expires_in' => self::accessTtlMinutes() * 60,
            'access_expires_at' => $accessExpires->toIso8601String(),
            'refresh_expires_at' => $refreshExpires->toIso8601String(),
        ];
    }

    /**
     * Merge Sanctum pair into a JSON payload. Keeps `token` as the access token for backward compatibility.
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function mergeTokenResponse(array $base, array $pair): array
    {
        return array_merge($base, [
            'token' => $pair['access_token'],
            // Explicit key for clients that expect access_token (Flutter, etc.)
            'access_token' => $pair['access_token'],
            'refresh_token' => $pair['refresh_token'],
            'expires_in' => $pair['expires_in'],
            'access_expires_at' => $pair['access_expires_at'],
            'token_type' => 'Bearer',
            'refresh_expires_at' => $pair['refresh_expires_at'],
        ]);
    }
}
