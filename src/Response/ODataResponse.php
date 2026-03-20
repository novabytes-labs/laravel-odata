<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Response;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class ODataResponse implements Responsable, Arrayable
{
    public function __construct(
        private readonly LengthAwarePaginator $paginator,
        private readonly bool $includeCount,
        private readonly string $format,
    ) {}

    /**
     * Convert the response to a JsonResponse.
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray(), 200);
    }

    /**
     * Convert the response to an array using the configured format.
     */
    public function toArray(): array
    {
        if ($this->format === 'odata') {
            return $this->toODataFormat();
        }

        return $this->toLaravelFormat();
    }

    /**
     * Format the response as OData JSON.
     *
     * @return array{
     *     @odata.count?: int,
     *     value: array<int, mixed>,
     *     @odata.nextLink?: string
     * }
     */
    private function toODataFormat(): array
    {
        $result = [];

        if ($this->includeCount) {
            $result['@odata.count'] = $this->paginator->total();
        }

        $result['value'] = $this->paginator->items();

        if ($this->paginator->hasMorePages()) {
            $result['@odata.nextLink'] = $this->buildNextLink();
        }

        return $result;
    }

    /**
     * Format the response as standard Laravel pagination.
     *
     * @return array{data: array<int, mixed>, meta?: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    private function toLaravelFormat(): array
    {
        $result = [
            'data' => $this->paginator->items(),
        ];

        if ($this->includeCount) {
            $result['meta'] = [
                'total' => $this->paginator->total(),
                'per_page' => $this->paginator->perPage(),
                'current_page' => $this->paginator->currentPage(),
                'last_page' => $this->paginator->lastPage(),
            ];
        }

        return $result;
    }

    /**
     * Build the next page URL with OData $skip and $top parameters.
     */
    private function buildNextLink(): string
    {
        $currentUrl = $this->paginator->path();
        $perPage = $this->paginator->perPage();
        $currentPage = $this->paginator->currentPage();
        $nextSkip = $currentPage * $perPage;

        $params = request()->query();
        $params['$skip'] = $nextSkip;
        $params['$top'] = $perPage;

        return $currentUrl . '?' . http_build_query($params);
    }

    /**
     * Get the paginated items as a Collection.
     */
    public function getCollection(): \Illuminate\Support\Collection
    {
        return collect($this->paginator->items());
    }

    /**
     * Get the total number of records.
     */
    public function total(): int
    {
        return $this->paginator->total();
    }
}
