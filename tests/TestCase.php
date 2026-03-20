<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests;

use NovaBytes\OData\Laravel\ODataServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ODataServiceProvider::class,
        ];
    }

    /**
     * Load test database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Configure the SQLite in-memory database for testing.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
