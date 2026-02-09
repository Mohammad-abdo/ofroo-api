<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddCorsHeaders
{
    /**
     * Add CORS headers to the response (for API error responses that bypass normal middleware).
     */
    public static function toResponse(Response $response, Request $request): Response
    {
        $origin = $request->headers->get('Origin');
        $allowed = config('cors.allowed_origins', []);
        $patterns = config('cors.allowed_origins_patterns', []);
        $allowed = is_array($allowed) ? $allowed : [];

        $allowOrigin = null;
        if ($origin) {
            if (in_array('*', $allowed) || in_array($origin, $allowed)) {
                $allowOrigin = $origin;
            }
            if (!$allowOrigin && !empty($patterns)) {
                foreach ($patterns as $pattern) {
                    if (@preg_match($pattern, $origin)) {
                        $allowOrigin = $origin;
                        break;
                    }
                }
            }
        }
        if (!$allowOrigin && config('cors.supports_credentials') !== true) {
            $allowOrigin = '*';
        }
        if ($allowOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        }
        $methods = config('cors.allowed_methods', ['*']);
        $response->headers->set('Access-Control-Allow-Methods', is_array($methods) ? implode(', ', $methods) : 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $headers = config('cors.allowed_headers', ['*']);
        $response->headers->set('Access-Control-Allow-Headers', is_array($headers) ? implode(', ', $headers) : 'Content-Type, Authorization, Accept, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', config('cors.supports_credentials', false) ? 'true' : 'false');
        $response->headers->set('Access-Control-Max-Age', (string) (config('cors.max_age', 0) ?: 86400));

        return $response;
    }

    /**
     * Handle an incoming request - ensure response has CORS headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            return self::toResponse($response, $request);
        }

        $response = $next($request);
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            return self::toResponse($response, $request);
        }
        return $response;
    }
}
