<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use NovaBytes\OData\Laravel\Exceptions\InvalidQueryException;

/**
 * Handles CRUD operations for OData entity sets, decoupled from HTTP.
 */
class ODataCrudHandler
{
    /**
     * Find a single entity by its primary key.
     *
     * @param class-string<Model> $modelClass
     * @throws ModelNotFoundException
     */
    public function findEntity(string $modelClass, mixed $key): Model
    {
        return $modelClass::query()->findOrFail($key);
    }

    /**
     * Create a new entity from the given data.
     *
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $data Raw request data (PascalCase or snake_case keys).
     */
    public function createEntity(string $modelClass, array $data, EntitySetDefinition $definition): Model
    {
        $snakeData = $this->convertAndValidateFields($data, $definition->allowedCreates, 'create');

        $model = new $modelClass();
        $model->fill($snakeData);
        $model->save();

        return $model->fresh();
    }

    /**
     * Update an existing entity with the given data.
     *
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $data Raw request data (PascalCase or snake_case keys).
     * @param bool $isPartial If true, this is a PATCH (merge). If false, this is a PUT (replace).
     */
    public function updateEntity(string $modelClass, mixed $key, array $data, bool $isPartial, EntitySetDefinition $definition): Model
    {
        $snakeData = $this->convertAndValidateFields($data, $definition->allowedUpdates, 'update');

        $model = $modelClass::query()->findOrFail($key);

        if (!$isPartial) {
            // PUT: set all updatable fields — unspecified ones get null
            foreach ($definition->allowedUpdates as $field) {
                if (!array_key_exists($field, $snakeData)) {
                    $snakeData[$field] = null;
                }
            }
        }

        $model->fill($snakeData);
        $model->save();

        return $model->fresh();
    }

    /**
     * Delete an entity by its primary key.
     *
     * @param class-string<Model> $modelClass
     * @throws ModelNotFoundException
     */
    public function deleteEntity(string $modelClass, mixed $key): void
    {
        $model = $modelClass::query()->findOrFail($key);
        $model->delete();
    }

    /**
     * Convert request data keys from PascalCase to snake_case and validate against allowed fields.
     *
     * @param array<string, mixed> $data
     * @param list<string> $allowedFields Snake_case field names.
     * @return array<string, mixed> Data with snake_case keys.
     * @throws InvalidQueryException
     */
    private function convertAndValidateFields(array $data, array $allowedFields, string $operation): array
    {
        $snakeData = [];
        $invalidFields = [];

        foreach ($data as $field => $value) {
            $snakeField = CaseConverter::toSnakeCase($field);

            if ($allowedFields !== [] && !in_array($snakeField, $allowedFields, true)) {
                $invalidFields[] = $field;

                continue;
            }

            $snakeData[$snakeField] = $value;
        }

        if ($invalidFields !== []) {
            throw new InvalidQueryException(
                "The following fields are not allowed for {$operation}: " . implode(', ', $invalidFields)
                . '. Allowed fields: ' . implode(', ', $allowedFields) . '.',
            );
        }

        return $snakeData;
    }
}
