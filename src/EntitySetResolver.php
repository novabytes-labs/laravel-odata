<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves entity set names to their model class and configuration.
 */
class EntitySetResolver
{
    /** @var array<string, EntitySetDefinition> Entity set name => definition (case-insensitive lookup). */
    private array $definitions = [];

    /** @var array<class-string, EntitySetDefinition> Model class => definition. */
    private array $byModelClass = [];

    /**
     * Register an entity set definition.
     *
     * @param class-string $modelClass
     * @param array<string, mixed> $config
     */
    public function register(string $modelClass, array $config): void
    {
        $defaultOperations = config('odata.crud.default_operations', ['read']);
        $entitySetName = $config['entitySet'] ?? $this->guessEntitySetName($modelClass);

        $definition = new EntitySetDefinition(
            modelClass: $modelClass,
            entitySetName: $entitySetName,
            operations: $config['operations'] ?? $defaultOperations,
            allowedFilters: $config['allowedFilters'] ?? [],
            allowedSorts: $config['allowedSorts'] ?? [],
            allowedExpands: $config['allowedExpands'] ?? [],
            allowedSelects: $config['allowedSelects'] ?? [],
            allowedCreates: $config['allowedCreates'] ?? [],
            allowedUpdates: $config['allowedUpdates'] ?? [],
        );

        $this->definitions[strtolower($entitySetName)] = $definition;
        $this->byModelClass[$modelClass] = $definition;
    }

    /**
     * Resolve an entity set name to its definition.
     */
    public function resolve(string $entitySetName): ?EntitySetDefinition
    {
        return $this->definitions[strtolower($entitySetName)] ?? null;
    }

    /**
     * Resolve a model class to its definition.
     *
     * @param class-string $modelClass
     */
    public function resolveByModelClass(string $modelClass): ?EntitySetDefinition
    {
        return $this->byModelClass[$modelClass] ?? null;
    }

    /**
     * Get all registered entity set definitions.
     *
     * @return array<string, EntitySetDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Guess the entity set name from the model class name by pluralizing.
     *
     * @param class-string $modelClass
     */
    private function guessEntitySetName(string $modelClass): string
    {
        $shortName = class_basename($modelClass);

        return str($shortName)->plural()->toString();
    }
}
