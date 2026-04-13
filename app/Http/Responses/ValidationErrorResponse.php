<?php

namespace App\Http\Responses;

class ValidationErrorResponse
{
    /**
     * Create a user-friendly validation error response
     *
     * @param array $errors Validation errors
     * @param string $message Optional message
     * @param int $statusCode HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function make(array $errors, string $message = 'Validation failed', int $statusCode = 422)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'status_code' => $statusCode,
        ], $statusCode);
    }
}
