<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Metadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NovaBytes\OData\Laravel\CaseConverter;
use NovaBytes\OData\Metadata\EdmTypeResolver;
use NovaBytes\OData\Metadata\EntityType;
use NovaBytes\OData\Metadata\NavigationPropertyMetadata;
use NovaBytes\OData\Metadata\PropertyMetadata;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Builds an EntityType from an Eloquent model class and its configuration.
 */
class EntityTypeBuilder
{
    /**
     * Build an EntityType from a model class and its entity set configuration.
     *
     * @param class-string<Model> $modelClass
     * @param array{entitySet?: string, allowedFilters?: list<string>, allowedSorts?: list<string>, allowedExpands?: list<string>, allowedSelects?: list<string>} $config
     */
    public static function build(string $modelClass, array $config = []): EntityType
    {
        $model = new $modelClass();
        $table = $model->getTable();
        $keyName = $model->getKeyName();
        $keyPascal = CaseConverter::toPascalCase($keyName);

        $entitySetName = $config['entitySet'] ?? Str::studly(Str::plural($table));

        $allowedFilters = $config['allowedFilters'] ?? [];
        $allowedSorts = $config['allowedSorts'] ?? [];
        $allowedSelects = $config['allowedSelects'] ?? [];
        $allowedExpands = $config['allowedExpands'] ?? [];

        $properties = self::buildProperties($table, $allowedFilters, $allowedSorts, $allowedSelects);
        $navigationProperties = self::buildNavigationProperties($model, $allowedExpands);

        return new EntityType(
            name: class_basename($modelClass),
            entitySetName: $entitySetName,
            keyProperty: $keyPascal,
            properties: $properties,
            navigationProperties: $navigationProperties,
        );
    }

    /**
     * Build property metadata from the database schema.
     *
     * @param list<string> $allowedFilters
     * @param list<string> $allowedSorts
     * @param list<string> $allowedSelects
     * @return list<PropertyMetadata>
     */
    private static function buildProperties(
        string $table,
        array $allowedFilters,
        array $allowedSorts,
        array $allowedSelects,
    ): array {
        $columns = Schema::getColumns($table);
        $properties = [];

        foreach ($columns as $column) {
            $name = $column['name'];
            $pascalName = CaseConverter::toPascalCase($name);
            $edmType = EdmTypeResolver::resolve($column['type_name']);

            $properties[] = new PropertyMetadata(
                name: $pascalName,
                edmType: $edmType,
                nullable: $column['nullable'],
                filterable: in_array($name, $allowedFilters, true),
                sortable: in_array($name, $allowedSorts, true),
                selectable: in_array($name, $allowedSelects, true),
            );
        }

        return $properties;
    }

    /**
     * Build navigation property metadata by reflecting on the model's relationship methods.
     *
     * @param list<string> $allowedExpands
     * @return list<NavigationPropertyMetadata>
     */
    private static function buildNavigationProperties(Model $model, array $allowedExpands): array
    {
        if ($allowedExpands === []) {
            return [];
        }

        $navigationProperties = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (!$returnType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();

            if (!is_subclass_of($typeName, Relation::class)) {
                continue;
            }

            $methodName = $method->getName();

            if (!in_array($methodName, $allowedExpands, true)) {
                continue;
            }

            $relation = $model->{$methodName}();
            $relatedModel = $relation->getRelated();
            $targetEntityType = class_basename($relatedModel);

            $isCollection = $relation instanceof HasMany
                || $relation instanceof BelongsToMany
                || $relation instanceof HasManyThrough
                || $relation instanceof MorphMany
                || $relation instanceof MorphToMany;

            $navigationProperties[] = new NavigationPropertyMetadata(
                name: CaseConverter::toPascalCase($methodName),
                targetEntityType: $targetEntityType,
                isCollection: $isCollection,
            );
        }

        return $navigationProperties;
    }
}
