<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Http;

use Illuminate\Support\Facades\Route;
use NovaBytes\OData\Laravel\EntitySetResolver;
use NovaBytes\OData\Laravel\Http\Controllers\ODataEntityController;

/**
 * Registers OData CRUD routes for all configured entity sets.
 */
class ODataRouteRegistrar
{
    /**
     * Register CRUD routes for all entity sets based on their allowed operations.
     */
    public static function register(EntitySetResolver $resolver): void
    {
        $prefix = config('odata.crud.route_prefix', 'api');
        $middleware = config('odata.crud.middleware', ['api']);

        Route::middleware($middleware)->prefix($prefix)->group(function () use ($resolver) {
            foreach ($resolver->all() as $definition) {
                $entitySet = $definition->entitySetName;

                if ($definition->supportsOperation('read') || $definition->supportsOperation('create')) {
                    self::registerCollectionRoutes($entitySet, $definition->operations);
                }

                if ($definition->supportsOperation('read') || $definition->supportsOperation('update') || $definition->supportsOperation('delete')) {
                    self::registerSingleEntityRoutes($entitySet, $definition->operations);
                }
            }
        });
    }

    /**
     * Register routes for the entity set collection endpoint.
     *
     * @param list<string> $operations
     */
    private static function registerCollectionRoutes(string $entitySet, array $operations): void
    {
        if (in_array('read', $operations, true)) {
            Route::get("{$entitySet}", [ODataEntityController::class, 'index'])
                ->name("odata.{$entitySet}.index");
        }

        if (in_array('create', $operations, true)) {
            Route::post("{$entitySet}", [ODataEntityController::class, 'store'])
                ->name("odata.{$entitySet}.store");
        }
    }

    /**
     * Register routes for single entity endpoints.
     *
     * Uses the pattern EntitySet/{key} where key matches any non-slash character.
     * The entity set name is embedded in the URL pattern (not as a route parameter).
     * The controller receives entitySet via a closure wrapper.
     *
     * @param list<string> $operations
     */
    private static function registerSingleEntityRoutes(string $entitySet, array $operations): void
    {
        $pattern = "{$entitySet}/{key}";
        $keyRegex = '[^/]+';

        if (in_array('read', $operations, true)) {
            Route::get($pattern, [ODataEntityController::class, 'show'])
                ->where('key', $keyRegex)
                ->name("odata.{$entitySet}.show");
        }

        if (in_array('update', $operations, true)) {
            Route::put($pattern, [ODataEntityController::class, 'update'])
                ->where('key', $keyRegex)
                ->name("odata.{$entitySet}.update");

            Route::patch($pattern, [ODataEntityController::class, 'patch'])
                ->where('key', $keyRegex)
                ->name("odata.{$entitySet}.patch");
        }

        if (in_array('delete', $operations, true)) {
            Route::delete($pattern, [ODataEntityController::class, 'destroy'])
                ->where('key', $keyRegex)
                ->name("odata.{$entitySet}.destroy");
        }
    }
}
