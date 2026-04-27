<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject long-lived refresh tokens on normal API routes (they must only be used with POST /auth/refresh).
 * Legacy tokens named "auth_token" remain allowed until they expire or are revoked.
 */
class EnsureTokenIsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if ($token && $token->name === ApiTokenService::NAME_REFRESH) {
            return response()->json([
                'message' => 'Access token required. Send the short-lived Bearer token, or call POST /auth/refresh with refresh_token.',
                'message_ar' => 'مطلوب توكن وصول (access). أرسل توكن الجلسة القصير في الهيدر، أو استخدم مسار تحديث التوكن.',
                'error' => 'invalid_token_type',
            ], 401);
        }

        return $next($request);
    }
}
