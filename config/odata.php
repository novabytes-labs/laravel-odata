<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Controls how query results are formatted.
    |
    | 'laravel' - Standard Laravel pagination/collection format.
    | 'odata'   - OData JSON format with @odata.count, value, @odata.nextLink.
    |
    */
    'response_format' => 'laravel',

    /*
    |--------------------------------------------------------------------------
    | Maximum Expand Depth
    |--------------------------------------------------------------------------
    |
    | Limits how deeply nested $expand can go to prevent abuse.
    | For example, $expand=Category($expand=Parent($expand=Root)) has depth 3.
    |
    */
    'max_expand_depth' => 3,

    /*
    |--------------------------------------------------------------------------
    | Maximum $top Value
    |--------------------------------------------------------------------------
    |
    | The maximum number of items a client can request via $top.
    | Set to null to allow any value.
    |
    */
    'max_top' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Default $top Value
    |--------------------------------------------------------------------------
    |
    | Applied when the client does not specify $top.
    | Set to null to return all results by default.
    |
    */
    'default_top' => null,

    /*
    |--------------------------------------------------------------------------
    | Throw on Invalid Query Options
    |--------------------------------------------------------------------------
    |
    | When true, requesting a filter, sort, expand, or select that is not in
    | the allowlist throws an InvalidQueryException (HTTP 400).
    |
    | When false, invalid options are silently ignored.
    |
    */
    'throw_on_invalid' => true,

    /*
    |--------------------------------------------------------------------------
    | Entity Sets
    |--------------------------------------------------------------------------
    |
    | Register Eloquent models as OData entity sets. Each entry defines the
    | model class and its allowed query capabilities. These are used by
    | both the $metadata / OpenAPI endpoints and as default allowlists
    | for ODataQueryBuilder (controllers can still override per-endpoint).
    |
    | Example:
    |   \App\Models\Product::class => [
    |       'entitySet'      => 'Products',
    |       'allowedFilters'  => ['name', 'price', 'is_active'],
    |       'allowedSorts'    => ['name', 'price', 'created_at'],
    |       'allowedExpands'  => ['category', 'reviews'],
    |       'allowedSelects'  => ['id', 'name', 'price', 'description'],
    |   ],
    |
    */
    'entity_sets' => [],

    /*
    |--------------------------------------------------------------------------
    | Schema Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace used in the OData CSDL metadata document.
    |
    */
    'namespace' => 'Default',

    /*
    |--------------------------------------------------------------------------
    | Metadata Endpoints
    |--------------------------------------------------------------------------
    |
    | When enabled, registers routes for OData $metadata (CSDL XML) and
    | OpenAPI (JSON) documentation, auto-generated from entity_sets.
    |
    */
    'metadata' => [
        'enabled' => false,
        'route_prefix' => 'api',
        'openapi' => [
            'title' => 'OData API',
            'version' => '1.0.0',
            'description' => '',
        ],
    ],

];
