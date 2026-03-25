<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Response;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Wraps a single Eloquent model as an OData-compliant JSON response.
 */
class ODataSingleEntityResponse implements Responsable, Arrayable
{
    /**
     * @param Model $model The entity to return.
     * @param int $statusCode The HTTP status code (200, 201, etc.).
     */
    public function __construct(
        private readonly Model $model,
        private readonly int $statusCode = 200,
    ) {}

    /**
     * Convert the response to a JsonResponse.
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray(), $this->statusCode);
    }

    /**
     * Convert the model to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->model->toArray();
    }
}
