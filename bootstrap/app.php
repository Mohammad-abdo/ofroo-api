<?php

use App\Http\Middleware\AddCorsHeaders;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CheckMerchant;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Mobile API Routes
            Route::middleware(['api'])
                ->prefix('api/mobile')
                ->group(base_path('routes/mobile.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdmin::class,
            'merchant' => CheckMerchant::class,
            'permission' => CheckPermission::class,
        ]);

        // CORS first so all API responses (including errors) can include headers; then JSON
        $middleware->api(prepend: [
            AddCorsHeaders::class,
            ForceJsonResponse::class,
        ]);

        // Rate limiting for auth endpoints
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Add CORS headers to API error responses so browser doesn't block them
        $addCors = function ($response, $request) {
            if ($response instanceof Response && ($request->is('api/*') || $request->expectsJson())) {
                return AddCorsHeaders::toResponse($response, $request);
            }
            return $response;
        };

        // Handle all exceptions for API routes
        $exceptions->render(function (Throwable $e, $request) use ($addCors) {
            // Check if this is an API route
            $isApiRoute = $request->is('api/*') || $request->expectsJson();
            
            if ($isApiRoute) {
                // Handle AuthenticationException
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $response = response()->json([
                        'message' => 'Unauthenticated',
                        'error' => 'You must be authenticated to access this resource'
                    ], 401);
                    return $addCors($response, $request);
                }
                
                // Handle ValidationException
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $response = response()->json([
                        'message' => 'Validation failed',
                        'errors' => $e->errors()
                    ], 422);
                    return $addCors($response, $request);
                }
                
                // Handle ModelNotFoundException
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $response = response()->json([
                        'message' => 'Resource not found',
                        'error' => 'The requested resource could not be found'
                    ], 404);
                    return $addCors($response, $request);
                }
                
                // Handle NotFoundHttpException
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $response = response()->json([
                        'message' => 'Route not found',
                        'error' => 'The requested endpoint does not exist'
                    ], 404);
                    return $addCors($response, $request);
                }
                
                // Handle MethodNotAllowedHttpException
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    $response = response()->json([
                        'message' => 'Method not allowed',
                        'error' => 'The HTTP method is not allowed for this endpoint'
                    ], 405);
                    return $addCors($response, $request);
                }
                
                // Handle all other exceptions (500 errors)
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $response = response()->json([
                    'message' => $e->getMessage() ?: 'Internal server error',
                    'error' => config('app.debug') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ] : 'An error occurred while processing your request'
                ], $statusCode);
                return $addCors($response, $request);
            }
        });
    })->create();
