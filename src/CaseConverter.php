<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

class CaseConverter
{
    /**
     * Convert a PascalCase or camelCase string to snake_case.
     *
     * Examples:
     *   'Price'      → 'price'
     *   'CategoryId' → 'category_id'
     *   'firstName'  → 'first_name'
     *   'already_snake' → 'already_snake'
     */
    public static function toSnakeCase(string $value): string
    {
        // Already snake_case — skip conversion
        if (str_contains($value, '_') || $value === strtolower($value)) {
            return $value;
        }

        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $value);

        return strtolower($result);
    }

    /**
     * Convert a PascalCase relationship name to camelCase.
     *
     * Examples:
     *   'Category'     → 'category'
     *   'OrderDetails' → 'orderDetails'
     *   'category'     → 'category'
     */
    public static function toCamelCase(string $value): string
    {
        return lcfirst($value);
    }
}
