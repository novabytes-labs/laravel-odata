<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Response;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * Formats an error response in the OData JSON error format.
 */
class ODataErrorResponse implements Responsable, Arrayable
{
    /**
     * @param string $code The error code.
     * @param string $message The human-readable error message.
     * @param int $statusCode The HTTP status code (400, 404, 405, 409, etc.).
     */
    public function __construct(
        private readonly string $code,
        private readonly string $message,
        private readonly int $statusCode = 400,
    ) {}

    /**
     * Create a 404 Not Found error response.
     */
    public static function notFound(string $entitySet, string $key): self
    {
        return new self(
            code: 'NotFound',
            message: "Entity '{$entitySet}' with key '{$key}' was not found.",
            statusCode: 404,
        );
    }

    /**
     * Create a 405 Method Not Allowed error response.
     */
    public static function methodNotAllowed(string $entitySet, string $operation): self
    {
        return new self(
            code: 'MethodNotAllowed',
            message: "Operation '{$operation}' is not allowed on entity set '{$entitySet}'.",
            statusCode: 405,
        );
    }

    /**
     * Create a 400 Bad Request error response for invalid fields.
     */
    public static function invalidFields(array $invalidFields, array $allowedFields): self
    {
        $invalid = implode(', ', $invalidFields);
        $allowed = implode(', ', $allowedFields);

        return new self(
            code: 'BadRequest',
            message: "The following fields are not allowed: {$invalid}. Allowed fields: {$allowed}.",
            statusCode: 400,
        );
    }

    /**
     * Convert the response to a JsonResponse.
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray(), $this->statusCode);
    }

    /**
     * Convert to the OData error format.
     *
     * @return array{error: array{code: string, message: string}}
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
        ];
    }
}
