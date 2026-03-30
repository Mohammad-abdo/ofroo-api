<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    protected function successWithPagination(
        $paginator,
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data ?? $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Created successfully', 201);
    }

    protected function updated(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Updated successfully', 200);
    }

    protected function deleted(?string $message = null): JsonResponse
    {
        return $this->success(null, $message ?? 'Deleted successfully', 200);
    }

    protected function error(
        string $message,
        int $statusCode = 400,
        array $errors = [],
        ?string $messageAr = null,
        ?string $messageEn = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($messageAr !== null) {
            $response['message_ar'] = $messageAr;
        }

        if ($messageEn !== null) {
            $response['message_en'] = $messageEn;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    protected function validationError(array $errors, ?string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Validation failed', 422, $errors);
    }

    protected function notFound(string $resource = 'Resource', ?string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? "{$resource} not found",
            404,
            [],
            "لم يتم العثور على {$resource}",
            "{$resource} not found"
        );
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function serverError(string $message = 'Internal server error', ?string $debugMessage = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (config('app.debug') && $debugMessage !== null) {
            $response['debug'] = $debugMessage;
        }

        return response()->json($response, 500);
    }
}
