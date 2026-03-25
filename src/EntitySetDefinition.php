<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

/**
 * Holds the resolved configuration for a single OData entity set.
 */
readonly class EntitySetDefinition
{
    /**
     * @param class-string $modelClass The fully qualified Eloquent model class name.
     * @param string $entitySetName The OData entity set name (e.g., "Products").
     * @param list<string> $operations Allowed CRUD operations: 'read', 'create', 'update', 'delete'.
     * @param list<string> $allowedFilters Properties allowed in $filter.
     * @param list<string> $allowedSorts Properties allowed in $orderby.
     * @param list<string> $allowedExpands Relationships allowed in $expand.
     * @param list<string> $allowedSelects Properties allowed in $select.
     * @param list<string> $allowedCreates Properties allowed in POST request bodies.
     * @param list<string> $allowedUpdates Properties allowed in PUT/PATCH request bodies.
     */
    public function __construct(
        public string $modelClass,
        public string $entitySetName,
        public array $operations = ['read'],
        public array $allowedFilters = [],
        public array $allowedSorts = [],
        public array $allowedExpands = [],
        public array $allowedSelects = [],
        public array $allowedCreates = [],
        public array $allowedUpdates = [],
    ) {}

    /**
     * Check whether this entity set supports a given operation.
     */
    public function supportsOperation(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }
}
