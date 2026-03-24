# Laravel OData

[![Latest Version on Packagist](https://img.shields.io/packagist/v/novabytes/laravel-odata.svg)](https://packagist.org/packages/novabytes/laravel-odata)
![Test Status](https://img.shields.io/github/actions/workflow/status/novabytes-labs/laravel-odata/ci.yml?label=tests&branch=master)
![Code Style Status](https://img.shields.io/github/actions/workflow/status/novabytes-labs/laravel-odata/ci.yml?label=code%20style&branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/novabytes/laravel-odata.svg)](https://packagist.org/packages/novabytes/laravel-odata)

Apply OData 4 query options to Eloquent models. Supports `$filter`, `$select`, `$expand`, `$orderby`, `$top`, `$skip`, and `$count`.

Built on top of [novabytes-labs/odata-query-parser](https://github.com/novabytes-labs/odata-query-parser).

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Query Options](#query-options)
- [PascalCase Conversion](#pascalcase-conversion)
- [Security](#security)
- [Entity Sets & Metadata](#entity-sets--metadata)
  - [Metadata Endpoints](#metadata-endpoints)
- [Configuration](#configuration)
- [Advanced Usage](#advanced-usage)
- [Requirements](#requirements)
- [License](#license)

## Installation

```bash
composer require novabytes/laravel-odata
```

Publish the config file:

```bash
php artisan vendor:publish --tag=odata-config
```

## Quick Start

```php
use NovaBytes\OData\Laravel\ODataQueryBuilder;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name', 'price', 'is_active', 'category_id')
            ->allowedSorts('name', 'price', 'created_at')
            ->allowedExpands('category', 'reviews')
            ->allowedSelects('id', 'name', 'price', 'description', 'category_id')
            ->get();
    }
}
```

Your API now accepts OData queries:

```
GET /products?$filter=Price gt 100&$select=Name,Price&$expand=Category&$orderby=Price desc&$top=50&$skip=10&$count=true
```

## Query Options

| Option | Example | Description |
|--------|---------|-------------|
| `$filter` | `Price gt 100`, `contains(Name,'Widget')`, `Reviews/any(r:r/Rating gt 4)` | Filter results using comparison operators, functions, and lambda expressions |
| `$select` | `Name,Price` | Choose which properties to return (primary key always included) |
| `$expand` | `Category`, `Reviews($filter=Rating gt 4;$top=5)` | Eager-load relationships with optional nested query options |
| `$orderby` | `Price desc`, `Name asc,Price desc` | Sort results by one or more properties |
| `$top` | `10` | Limit the number of results |
| `$skip` | `20` | Skip a number of results (for pagination) |
| `$count` | `true` | Include total count in the response |

All OData 4 comparison operators (`eq`, `ne`, `gt`, `ge`, `lt`, `le`), logical operators (`and`, `or`, `not`), and 30+ built-in functions are supported. See the [parser README](https://github.com/novabytes-labs/odata-query-parser) for the full list.

## PascalCase Conversion

OData uses PascalCase property names. This package automatically converts them to Eloquent's snake_case:

| OData | Eloquent |
|---|---|
| `Price` | `price` |
| `CategoryId` | `category_id` |
| `IsActive` | `is_active` |
| `Category` (expand) | `category` (relationship) |

Define your allowlists in snake_case — the conversion is handled for you.

## Security

Every filterable, sortable, expandable, and selectable field must be explicitly whitelisted. Any request for a non-whitelisted field throws a `400 Bad Request` by default.

If no allowlist is set for a given operation, that operation is unrestricted.

## Entity Sets & Metadata

Register your models and their allowlists centrally in `config/odata.php` to enable:
- Auto-generated `$metadata` (CSDL XML) endpoint for OData clients
- Auto-generated OpenAPI 3.0 spec for human-readable documentation
- Shared allowlists — no need to repeat `allowedFilters()` etc. in every controller

```php
// config/odata.php

'entity_sets' => [
    \App\Models\Product::class => [
        'entitySet'       => 'Products',           // optional, auto-generated from table name
        'allowedFilters'  => ['name', 'price', 'is_active'],
        'allowedSorts'    => ['name', 'price', 'created_at'],
        'allowedExpands'  => ['category', 'reviews'],
        'allowedSelects'  => ['id', 'name', 'price', 'description'],
    ],
    \App\Models\Category::class => [
        'allowedFilters'  => ['name'],
        'allowedExpands'  => ['products'],
    ],
],

'namespace' => 'Default',

'metadata' => [
    'enabled' => true,
    'route_prefix' => 'odata',
    'openapi' => [
        'title' => 'My OData API',
        'version' => '1.0.0',
        'description' => '',
    ],
],
```

When `entity_sets` are configured, controllers can omit explicit allowlists:

```php
// Allowlists are loaded from config automatically
ODataQueryBuilder::for(Product::class, $request)->get();

// Explicit calls still override config when you need to restrict further
ODataQueryBuilder::for(Product::class, $request)
    ->allowedFilters('name')  // overrides config for this endpoint
    ->get();
```

### Metadata Endpoints

When `metadata.enabled` is `true`, two routes are registered automatically:

| Endpoint | Content-Type | Description |
|----------|-------------|-------------|
| `GET {prefix}/$metadata` | `application/xml` | OData v4 CSDL document — used by Power BI, Excel, and OData client libraries |
| `GET {prefix}/openapi.json` | `application/json` | OpenAPI 3.0 spec — use with Swagger UI, Redoc, or any API docs tool |

Both are auto-generated from your Eloquent models and the `entity_sets` config. Zero annotations needed — columns, types, and nullability are discovered from the database schema; relationships are discovered via reflection.

## Configuration

```php
// config/odata.php

return [
    'response_format'  => 'laravel',  // 'laravel' or 'odata'
    'max_expand_depth' => 3,          // Max $expand nesting depth
    'max_top'          => 1000,       // Max $top value (null = unlimited)
    'default_top'      => null,       // Default $top when not specified (null = no limit)
    'throw_on_invalid' => true,       // true = 400 on invalid ops, false = silently ignore
    'entity_sets'      => [],         // Model registrations (see above)
    'namespace'        => 'Default',  // CSDL schema namespace
    'metadata'         => [           // Metadata endpoint config
        'enabled'      => false,
        'route_prefix' => 'odata',
        'openapi'      => ['title' => 'OData API', 'version' => '1.0.0', 'description' => ''],
    ],
];
```

## Advanced Usage

### Using `toBuilder()`

```php
$builder = ODataQueryBuilder::for(Product::class, $request)
    ->allowedFilters('price')
    ->toBuilder();

$results = $builder->where('is_active', true)->get();
```

### Existing query as starting point

```php
$query = Product::where('is_active', true);

$results = ODataQueryBuilder::for($query, $request)
    ->allowedFilters('name', 'price')
    ->get();
```

## Requirements

- PHP >= 8.2
- Laravel 11, 12, or 13

## License

MIT
