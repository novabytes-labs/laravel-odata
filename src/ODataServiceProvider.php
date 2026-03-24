<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NovaBytes\OData\Laravel\Http\Controllers\MetadataController;
use NovaBytes\OData\Laravel\Http\Controllers\OpenApiController;
use NovaBytes\OData\Laravel\Metadata\EntitySetRegistry;

class ODataServiceProvider extends ServiceProvider
{
    /**
     * Register the OData configuration and services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/odata.php', 'odata');

        $this->app->singleton(EntitySetRegistry::class);
    }

    /**
     * Boot the OData services: publish config, register entity sets, and register metadata routes.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/odata.php' => config_path('odata.php'),
        ], 'odata-config');

        $this->registerEntitySets();
        $this->registerMetadataRoutes();
    }

    /**
     * Register entity set configurations into the registry for lazy building.
     */
    private function registerEntitySets(): void
    {
        $entitySets = config('odata.entity_sets', []);

        if ($entitySets === []) {
            return;
        }

        $registry = $this->app->make(EntitySetRegistry::class);

        foreach ($entitySets as $modelClass => $config) {
            $registry->register($modelClass, $config);
        }
    }

    /**
     * Register the $metadata and openapi.json routes when metadata is enabled.
     */
    private function registerMetadataRoutes(): void
    {
        if (!config('odata.metadata.enabled', false)) {
            return;
        }

        $prefix = config('odata.metadata.route_prefix', 'odata');

        Route::get("{$prefix}/\$metadata", MetadataController::class);
        Route::get("{$prefix}/openapi.json", OpenApiController::class);
    }
}
