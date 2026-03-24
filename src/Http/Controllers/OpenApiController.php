<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use NovaBytes\OData\Laravel\Metadata\EntitySetRegistry;
use NovaBytes\OData\Metadata\OpenApiGenerator;

/**
 * Serves the OpenAPI 3.0 JSON specification.
 */
class OpenApiController
{
    /**
     * Return the OpenAPI specification as JSON.
     */
    public function __invoke(EntitySetRegistry $registry): JsonResponse
    {
        $entityTypes = array_values($registry->all());
        $options = config('odata.metadata.openapi', []);
        $options['routePrefix'] = config('odata.metadata.route_prefix', 'odata');

        $spec = OpenApiGenerator::generate($entityTypes, $options);

        return new JsonResponse($spec);
    }
}
