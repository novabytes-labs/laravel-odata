<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Metadata;

use NovaBytes\OData\Metadata\EntityType;

/**
 * Singleton registry that lazily builds and holds all registered OData entity types.
 */
class EntitySetRegistry
{
    /** @var array<class-string, array<string, mixed>> */
    private array $configs = [];

    /** @var array<class-string, EntityType> */
    private array $entityTypes = [];

    private bool $resolved = false;

    /**
     * Register a model class with its entity set configuration for lazy building.
     *
     * @param class-string $modelClass
     * @param array<string, mixed> $config
     */
    public function register(string $modelClass, array $config): void
    {
        $this->configs[$modelClass] = $config;
        $this->resolved = false;
    }

    /**
     * Get the entity type for a model class.
     *
     * @param class-string $modelClass
     */
    public function get(string $modelClass): ?EntityType
    {
        $this->resolve();

        return $this->entityTypes[$modelClass] ?? null;
    }

    /**
     * Get all registered entity types.
     *
     * @return array<class-string, EntityType>
     */
    public function all(): array
    {
        $this->resolve();

        return $this->entityTypes;
    }

    /**
     * Lazily build all entity types from registered configs.
     */
    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        foreach ($this->configs as $modelClass => $config) {
            if (!isset($this->entityTypes[$modelClass])) {
                $this->entityTypes[$modelClass] = EntityTypeBuilder::build($modelClass, $config);
            }
        }

        $this->resolved = true;
    }
}
