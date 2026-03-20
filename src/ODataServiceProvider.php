<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

use Illuminate\Support\ServiceProvider;

class ODataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/odata.php', 'odata');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/odata.php' => config_path('odata.php'),
        ], 'odata-config');
    }
}
