<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use NovaBytes\OData\Laravel\EntitySetResolver;
use NovaBytes\OData\Laravel\ODataCrudHandler;
use NovaBytes\OData\Laravel\ODataQueryBuilder;
use NovaBytes\OData\Laravel\Response\ODataErrorResponse;
use NovaBytes\OData\Laravel\Response\ODataSingleEntityResponse;

/**
 * Generic OData CRUD controller for auto-registered entity set routes.
 */
class ODataEntityController extends Controller
{
    /**
     * @param EntitySetResolver $resolver Resolves entity set names to model classes and config.
     * @param ODataCrudHandler $handler Handles CRUD operations.
     */
    public function __construct(
        private readonly EntitySetResolver $resolver,
        private readonly ODataCrudHandler $handler,
    ) {}

    /**
     * List entities in an entity set with OData query options.
     *
     * GET /{prefix}/{EntitySet}
     */
    public function index(Request $request): JsonResponse
    {
        $entitySet = $this->resolveEntitySetFromRoute($request);
        $definition = $this->resolver->resolve($entitySet);

        if ($definition === null) {
            return ODataErrorResponse::notFound($entitySet, '')->toResponse($request);
        }

        if (!$definition->supportsOperation('read')) {
            return ODataErrorResponse::methodNotAllowed($entitySet, 'read')->toResponse($request);
        }

        $builder = ODataQueryBuilder::for($definition->modelClass, $request);

        if ($definition->allowedFilters !== []) {
            $builder->allowedFilters(...$definition->allowedFilters);
        }

        if ($definition->allowedSorts !== []) {
            $builder->allowedSorts(...$definition->allowedSorts);
        }

        if ($definition->allowedExpands !== []) {
            $builder->allowedExpands(...$definition->allowedExpands);
        }

        if ($definition->allowedSelects !== []) {
            $builder->allowedSelects(...$definition->allowedSelects);
        }

        $result = $builder->get();

        if ($result instanceof \Illuminate\Contracts\Support\Responsable) {
            return $result->toResponse($request);
        }

        return new JsonResponse(['value' => $result->toArray()]);
    }

    /**
     * Get a single entity by key.
     *
     * GET /{prefix}/{EntitySet}/{key}
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $key = $this->normalizeKey($key);
        $entitySet = $this->resolveEntitySetFromRoute($request);
        $definition = $this->resolver->resolve($entitySet);

        if ($definition === null) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        if (!$definition->supportsOperation('read')) {
            return ODataErrorResponse::methodNotAllowed($entitySet, 'read')->toResponse($request);
        }

        try {
            $model = $this->handler->findEntity($definition->modelClass, $key);
        } catch (ModelNotFoundException) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        return (new ODataSingleEntityResponse($model))->toResponse($request);
    }

    /**
     * Create a new entity.
     *
     * POST /{prefix}/{EntitySet}
     */
    public function store(Request $request): JsonResponse
    {
        $entitySet = $this->resolveEntitySetFromRoute($request);
        $definition = $this->resolver->resolve($entitySet);

        if ($definition === null) {
            return ODataErrorResponse::notFound($entitySet, '')->toResponse($request);
        }

        if (!$definition->supportsOperation('create')) {
            return ODataErrorResponse::methodNotAllowed($entitySet, 'create')->toResponse($request);
        }

        $model = $this->handler->createEntity($definition->modelClass, $request->all(), $definition);

        return (new ODataSingleEntityResponse($model, 201))->toResponse($request);
    }

    /**
     * Full replace of an entity (PUT).
     *
     * PUT /{prefix}/{EntitySet}/{key}
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $key = $this->normalizeKey($key);
        $entitySet = $this->resolveEntitySetFromRoute($request);
        $definition = $this->resolver->resolve($entitySet);

        if ($definition === null) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        if (!$definition->supportsOperation('update')) {
            return ODataErrorResponse::methodNotAllowed($entitySet, 'update')->toResponse($request);
        }

        try {
            $model = $this->handler->updateEntity($definition->modelClass, $key, $request->all(), false, $definition);
        } catch (ModelNotFoundException) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        return (new ODataSingleEntityResponse($model))->toResponse($request);
    }

    /**
     * Partial update of an entity (PATCH).
     *
     * PATCH /{prefix}/{EntitySet}/{key}
     */
    public function patch(Request $request, string $key): JsonResponse
    {
        $key = $this->normalizeKey($key);
        $entitySet = $this->resolveEntitySetFromRoute($request);
        $definition = $this->resolver->resolve($entitySet);

        if ($definition === null) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        if (!$definition->supportsOperation('update')) {
            return ODataErrorResponse::methodNotAllowed($entitySet, 'update')->toResponse($request);
        }

        try {
            $model = $this->handler->updateEntity($definition->modelClass, $key, $request->all(), true, $definition);
        } catch (ModelNotFoundException) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        return (new ODataSingleEntityResponse($model))->toResponse($request);
    }

    /**
     * Delete an entity.
     *
     * DELETE /{prefix}/{EntitySet}/{key}
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $key = $this->normalizeKey($key);
        $entitySet = $this->resolveEntitySetFromRoute($request);
        $definition = $this->resolver->resolve($entitySet);

        if ($definition === null) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        if (!$definition->supportsOperation('delete')) {
            return ODataErrorResponse::methodNotAllowed($entitySet, 'delete')->toResponse($request);
        }

        try {
            $this->handler->deleteEntity($definition->modelClass, $key);
        } catch (ModelNotFoundException) {
            return ODataErrorResponse::notFound($entitySet, $key)->toResponse($request);
        }

        return new JsonResponse(null, 204);
    }

    /**
     * Normalize a key value by stripping OData parentheses and single quotes.
     *
     * Handles: "(1)" → "1", "('abc')" → "abc", "1" → "1"
     */
    private function normalizeKey(string $key): string
    {
        if (str_starts_with($key, '(') && str_ends_with($key, ')')) {
            $key = substr($key, 1, -1);
        }

        if (str_starts_with($key, "'") && str_ends_with($key, "'")) {
            $key = substr($key, 1, -1);
            $key = str_replace("''", "'", $key);
        }

        return $key;
    }

    /**
     * Resolve the entity set name from the route name.
     *
     * Route names follow the pattern: odata.{EntitySet}.{action}
     */
    private function resolveEntitySetFromRoute(Request $request): string
    {
        $routeName = $request->route()?->getName() ?? '';

        // Extract entity set from route name "odata.Products.show" → "Products"
        if (preg_match('/^odata\.(.+)\.\w+$/', $routeName, $matches)) {
            return $matches[1];
        }

        // Fallback: extract from URL path
        $path = trim($request->path(), '/');
        $prefix = config('odata.crud.route_prefix', 'api');

        if ($prefix !== '' && str_starts_with($path, $prefix . '/')) {
            $path = substr($path, strlen($prefix) + 1);
        }

        // First segment is the entity set name
        $segments = explode('/', $path);

        return $segments[0] ?? '';
    }
}
