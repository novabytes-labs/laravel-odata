# Laravel OData

Apply OData 4 query options to Eloquent models. Supports `$filter`, `$select`, `$expand`, `$orderby`, `$top`, `$skip`, and `$count`.

Built on top of [novabytes/odata-query-parser](https://github.com/novabytes/odata-query-parser).

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
GET /products?$filter=Price gt 100 and contains(Name,'Widget')
              &$select=Name,Price
              &$expand=Category($select=Name;$top=5)
              &$orderby=Price desc
              &$top=50&$skip=10&$count=true
```

## Usage

### Filtering (`$filter`)

```
GET /products?$filter=Price gt 100
GET /products?$filter=Name eq 'Milk'
GET /products?$filter=Price gt 5 and Price lt 20
GET /products?$filter=Name eq 'Milk' or Name eq 'Cheese'
GET /products?$filter=contains(Name,'Widget')
GET /products?$filter=startswith(Name,'Ch')
GET /products?$filter=IsActive eq true
GET /products?$filter=Name in ('Milk','Cheese','Butter')
GET /products?$filter=Description eq null
GET /products?$filter=Reviews/any(r:r/Rating gt 4)
```

All OData comparison operators (`eq`, `ne`, `gt`, `ge`, `lt`, `le`), logical operators (`and`, `or`, `not`), and 30+ built-in functions are supported by the parser. See the [parser README](https://github.com/novabytes/odata-query-parser) for the full list.

### Selecting (`$select`)

```
GET /products?$select=Name,Price
GET /products?$select=*
```

The primary key is always included automatically to ensure relationships work correctly.

### Expanding (`$expand`)

```
GET /products?$expand=Category
GET /products?$expand=Category,Reviews
GET /products?$expand=Reviews($filter=Rating gt 4;$top=5;$orderby=Rating desc)
```

Nested query options inside `$expand` are separated by `;` (per the OData spec) and support `$filter`, `$select`, `$orderby`, `$top`, and `$skip`.

### Sorting (`$orderby`)

```
GET /products?$orderby=Name asc
GET /products?$orderby=Price desc
GET /products?$orderby=IsActive desc,Price asc
```

### Pagination (`$top`, `$skip`)

```
GET /products?$top=10
GET /products?$top=10&$skip=20
```

### Count (`$count`)

```
GET /products?$count=true&$top=10
```

When `$count=true`, the response includes total count metadata and returns paginated results.

## PascalCase Conversion

OData uses PascalCase property names (`Price`, `CategoryId`, `IsActive`). This package automatically converts them to Eloquent's snake_case convention:

| OData | Eloquent |
|---|---|
| `Price` | `price` |
| `CategoryId` | `category_id` |
| `IsActive` | `is_active` |
| `CreatedAt` | `created_at` |
| `Category` (expand) | `category` (relationship) |

You define your allowlists in snake_case — the conversion is handled for you.

## Security

### Allowlists

Every filterable, sortable, expandable, and selectable field must be explicitly whitelisted. Any request for a field not in the allowlist throws a `400 Bad Request` by default.

```php
ODataQueryBuilder::for(Product::class, $request)
    ->allowedFilters('name', 'price')       // Only these can be filtered
    ->allowedSorts('name', 'created_at')    // Only these can be sorted
    ->allowedExpands('category')            // Only these can be expanded
    ->allowedSelects('id', 'name', 'price') // Only these can be selected
    ->get();
```

If no allowlist is set for a given operation (e.g. `allowedFilters` is never called), that operation is unrestricted.

### Expand Depth Limit

Prevents abuse via deeply nested `$expand`:

```php
// config/odata.php
'max_expand_depth' => 3, // default
```

`$expand=A($expand=B($expand=C($expand=D)))` would be rejected at depth 4.

### Top Limit

Prevents clients from requesting excessive result sets:

```php
// config/odata.php
'max_top' => 1000, // default
```

## Configuration

```php
// config/odata.php

return [
    // 'laravel' or 'odata'
    'response_format' => 'laravel',

    // Maximum $expand nesting depth
    'max_expand_depth' => 3,

    // Maximum $top value (null = unlimited)
    'max_top' => 1000,

    // Default $top when client doesn't specify (null = no limit)
    'default_top' => null,

    // true = throw 400 on invalid operations, false = silently ignore
    'throw_on_invalid' => true,
];
```

## Response Format

### Laravel format (default)

```json
{
    "data": [
        {"id": 1, "name": "Laptop", "price": 999.99}
    ],
    "meta": {
        "total": 100,
        "per_page": 10,
        "current_page": 1,
        "last_page": 10
    }
}
```

The `meta` key is only included when `$count=true`.

### OData format

Set `response_format` to `'odata'` in config:

```json
{
    "@odata.count": 100,
    "value": [
        {"id": 1, "name": "Laptop", "price": 999.99}
    ],
    "@odata.nextLink": "/products?$skip=10&$top=10"
}
```

## Advanced Usage

### Using `toBuilder()`

Get the Eloquent builder back for further customization:

```php
$builder = ODataQueryBuilder::for(Product::class, $request)
    ->allowedFilters('price')
    ->allowedSorts('name')
    ->toBuilder();

// Add your own conditions
$results = $builder
    ->where('is_active', true)
    ->withCount('reviews')
    ->get();
```

### Existing query as starting point

Pass an existing builder instead of a model class:

```php
$query = Product::where('is_active', true);

$results = ODataQueryBuilder::for($query, $request)
    ->allowedFilters('name', 'price')
    ->get();
```

### Lambda expressions

OData lambda expressions (`any`/`all`) are translated to Eloquent's `whereHas`/`whereDoesntHave`:

```
GET /products?$filter=Reviews/any(r:r/Rating gt 4)
```

Translates to:

```php
Product::whereHas('reviews', fn($q) => $q->where('rating', '>', 4))
```

## Requirements

- PHP >= 8.2
- Laravel 11, 12, or 13

## License

MIT
