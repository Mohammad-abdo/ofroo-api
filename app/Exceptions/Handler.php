<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->handleApiException($e);
            }
        });
    }

    protected function handleApiException(Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'message_ar' => 'غير مصرح',
                'message_en' => 'Unauthenticated',
            ], 401);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'message_ar' => 'المورد غير موجود',
                'message_en' => 'Resource not found',
            ], 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'message_ar' => 'الطريقة غير مسموحة',
                'message_en' => 'Method not allowed',
            ], 405);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'HTTP Error',
                'message_ar' => $e->getMessage() ?: 'خطأ HTTP',
                'message_en' => $e->getMessage() ?: 'HTTP Error',
            ], $e->getStatusCode());
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'message_ar' => 'محظور',
                'message_en' => 'Forbidden',
            ], 403);
        }

        if ($e instanceof \Illuminate\Database\QueryException) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'message_ar' => 'حدث خطأ في قاعدة البيانات',
                'message_en' => 'Database error occurred',
            ], 500);
        }

        if (config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Internal server error',
            'message_ar' => 'خطأ داخلي في الخادم',
            'message_en' => 'Internal server error',
        ], 500);
    }
}
