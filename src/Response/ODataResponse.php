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

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray(), 200);
    }

    public function toArray(): array
    {
        if ($this->format === 'odata') {
            return $this->toODataFormat();
        }

        return $this->toLaravelFormat();
    }

    /**
     * OData JSON format:
     * {
     *   "@odata.count": 100,
     *   "value": [...],
     *   "@odata.nextLink": "...?$skip=20&$top=10"
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
     * Standard Laravel pagination format.
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

    private function buildNextLink(): string
    {
        $currentUrl = $this->paginator->path();
        $perPage = $this->paginator->perPage();
        $currentPage = $this->paginator->currentPage();
        $nextSkip = $currentPage * $perPage;

        // Rebuild query string with OData parameters
        $params = request()->query();
        $params['$skip'] = $nextSkip;
        $params['$top'] = $perPage;

        return $currentUrl . '?' . http_build_query($params);
    }

    public function getCollection(): \Illuminate\Support\Collection
    {
        return collect($this->paginator->items());
    }

    public function total(): int
    {
        return $this->paginator->total();
    }
}
