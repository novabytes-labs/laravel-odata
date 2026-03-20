<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use NovaBytes\OData\AST\Expand\ExpandItem;
use NovaBytes\OData\AST\Expression;
use NovaBytes\OData\AST\Filter\PropertyPath;
use NovaBytes\OData\AST\OrderBy\OrderByItem;
use NovaBytes\OData\AST\OrderBy\SortDirection;
use NovaBytes\OData\AST\QueryOptions;
use NovaBytes\OData\AST\Select\SelectItem;
use NovaBytes\OData\Laravel\Exceptions\InvalidQueryException;
use NovaBytes\OData\Laravel\Response\ODataResponse;
use NovaBytes\OData\Parser\QueryOptionParser;

class ODataQueryBuilder
{
    private Builder $builder;
    private QueryOptions $queryOptions;

    /** @var list<string>|null */
    private ?array $allowedFilters = null;

    /** @var list<string>|null */
    private ?array $allowedSorts = null;

    /** @var list<string>|null */
    private ?array $allowedExpands = null;

    /** @var list<string>|null */
    private ?array $allowedSelects = null;

    private bool $throwOnInvalid;
    private int $maxExpandDepth;
    private ?int $maxTop;
    private ?int $defaultTop;
    private string $responseFormat;

    /**
     * @param class-string<Model>|Builder $subject
     */
    public static function for(string|Builder $subject, Request $request): self
    {
        return new self($subject, $request);
    }

    /**
     * @param class-string<Model>|Builder $subject
     */
    public function __construct(string|Builder $subject, Request $request)
    {
        $this->builder = $subject instanceof Builder
            ? $subject
            : $subject::query();

        $queryString = $request->server->get('QUERY_STRING', '');
        $this->queryOptions = QueryOptionParser::parse($queryString);

        $this->throwOnInvalid = config('odata.throw_on_invalid', true);
        $this->maxExpandDepth = config('odata.max_expand_depth', 3);
        $this->maxTop = config('odata.max_top', 1000);
        $this->defaultTop = config('odata.default_top');
        $this->responseFormat = config('odata.response_format', 'laravel');
    }

    /**
     * @param list<string> $filters Column names in snake_case.
     */
    public function allowedFilters(string ...$filters): self
    {
        $this->allowedFilters = $filters;

        return $this;
    }

    /**
     * @param list<string> $sorts Column names in snake_case.
     */
    public function allowedSorts(string ...$sorts): self
    {
        $this->allowedSorts = $sorts;

        return $this;
    }

    /**
     * @param list<string> $expands Relationship names in camelCase. Dot notation for nested.
     */
    public function allowedExpands(string ...$expands): self
    {
        $this->allowedExpands = $expands;

        return $this;
    }

    /**
     * @param list<string> $selects Column names in snake_case.
     */
    public function allowedSelects(string ...$selects): self
    {
        $this->allowedSelects = $selects;

        return $this;
    }

    /**
     * Execute the query and return results.
     *
     * When $count is true or response_format is 'odata', returns paginated results.
     * Otherwise returns a Collection.
     */
    public function get(): ODataResponse|Collection
    {
        $this->applyFilter();
        $this->applySelect();
        $this->applyExpand();
        $this->applyOrderBy();

        $top = $this->resolveTop();
        $skip = $this->queryOptions->skip;

        if ($this->responseFormat === 'odata' || $this->queryOptions->count === true) {
            return $this->executeWithPagination($top, $skip);
        }

        if ($top !== null) {
            $this->builder->limit($top);
        }

        if ($skip !== null) {
            $this->builder->offset($skip);
        }

        return $this->builder->get();
    }

    /**
     * Get the underlying Eloquent builder for further customization.
     */
    public function toBuilder(): Builder
    {
        $this->applyFilter();
        $this->applySelect();
        $this->applyExpand();
        $this->applyOrderBy();

        $top = $this->resolveTop();
        $skip = $this->queryOptions->skip;

        if ($top !== null) {
            $this->builder->limit($top);
        }

        if ($skip !== null) {
            $this->builder->offset($skip);
        }

        return $this->builder;
    }

    /**
     * Apply the $filter query option to the builder.
     */
    private function applyFilter(): void
    {
        if ($this->queryOptions->filter === null) {
            return;
        }

        if ($this->allowedFilters !== null && !$this->validateFilterColumns($this->queryOptions->filter)) {
            return;
        }

        $visitor = new EloquentFilterVisitor($this->builder, $this->allowedFilters ?? []);
        $visitor->apply($this->queryOptions->filter);
    }

    /**
     * Apply the $select query option to the builder.
     */
    private function applySelect(): void
    {
        if ($this->queryOptions->select === null) {
            return;
        }

        $columns = [];

        foreach ($this->queryOptions->select as $item) {
            if ($item->isWildcard) {
                return;
            }

            $column = $this->selectItemToColumn($item);

            if ($this->allowedSelects !== null && !in_array($column, $this->allowedSelects, true)) {
                if ($this->throwOnInvalid) {
                    throw InvalidQueryException::invalidSelect($column, $this->allowedSelects);
                }

                continue;
            }

            $columns[] = $column;
        }

        if (!empty($columns)) {
            $primaryKey = $this->builder->getModel()->getKeyName();
            if (!in_array($primaryKey, $columns, true)) {
                array_unshift($columns, $primaryKey);
            }

            $this->builder->select($columns);
        }
    }

    /**
     * Apply the $expand query option to eager-load relationships.
     */
    private function applyExpand(): void
    {
        if ($this->queryOptions->expand === null) {
            return;
        }

        $with = [];

        foreach ($this->queryOptions->expand as $expandItem) {
            if ($expandItem->isWildcard) {
                continue;
            }

            $relation = $this->expandItemToRelation($expandItem);
            $depth = substr_count($relation, '.') + 1;

            if ($depth > $this->maxExpandDepth) {
                if ($this->throwOnInvalid) {
                    throw InvalidQueryException::expandDepthExceeded($depth, $this->maxExpandDepth);
                }

                continue;
            }

            if ($this->allowedExpands !== null && !$this->isExpandAllowed($relation)) {
                if ($this->throwOnInvalid) {
                    throw InvalidQueryException::invalidExpand($relation, $this->allowedExpands);
                }

                continue;
            }

            if ($expandItem->nestedOptions !== null) {
                $with[$relation] = function (Builder|\Illuminate\Database\Eloquent\Relations\Relation $q) use ($expandItem) {
                    $this->applyNestedOptions($q, $expandItem->nestedOptions);
                };
            } else {
                $with[] = $relation;
            }
        }

        if (!empty($with)) {
            $this->builder->with($with);
        }
    }

    /**
     * Apply nested query options ($filter, $select, $orderby, $top, $skip) to an expanded relationship.
     */
    private function applyNestedOptions(Builder|\Illuminate\Database\Eloquent\Relations\Relation $query, QueryOptions $options): void
    {
        if ($options->filter !== null) {
            $builder = $query instanceof Builder ? $query : $query->getQuery();
            if ($query instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $eloquentBuilder = $query->getQuery();
            } else {
                $eloquentBuilder = $query;
            }
            $visitor = new EloquentFilterVisitor($eloquentBuilder, $this->allowedFilters ?? []);
            $visitor->apply($options->filter);
        }

        if ($options->select !== null) {
            $columns = [];
            foreach ($options->select as $item) {
                if ($item->isWildcard) {
                    $columns = [];
                    break;
                }
                $columns[] = $this->selectItemToColumn($item);
            }
            if (!empty($columns)) {
                $query->select($columns);
            }
        }

        if ($options->orderby !== null) {
            foreach ($options->orderby as $orderByItem) {
                $column = $this->orderByItemToColumn($orderByItem);
                $direction = $orderByItem->direction === SortDirection::Desc ? 'desc' : 'asc';
                $query->orderBy($column, $direction);
            }
        }

        if ($options->top !== null) {
            $query->limit($options->top);
        }

        if ($options->skip !== null) {
            $query->offset($options->skip);
        }
    }

    /**
     * Apply the $orderby query option to the builder.
     */
    private function applyOrderBy(): void
    {
        if ($this->queryOptions->orderby === null) {
            return;
        }

        foreach ($this->queryOptions->orderby as $orderByItem) {
            $column = $this->orderByItemToColumn($orderByItem);

            if ($this->allowedSorts !== null && !in_array($column, $this->allowedSorts, true)) {
                if ($this->throwOnInvalid) {
                    throw InvalidQueryException::invalidSort($column, $this->allowedSorts);
                }

                continue;
            }

            $direction = $orderByItem->direction === SortDirection::Desc ? 'desc' : 'asc';
            $this->builder->orderBy($column, $direction);
        }
    }

    /**
     * Resolve the effective $top value, applying max_top and default_top config.
     */
    private function resolveTop(): ?int
    {
        $top = $this->queryOptions->top ?? $this->defaultTop;

        if ($top !== null && $this->maxTop !== null && $top > $this->maxTop) {
            if ($this->throwOnInvalid) {
                throw InvalidQueryException::topExceeded($top, $this->maxTop);
            }

            $top = $this->maxTop;
        }

        return $top;
    }

    /**
     * Execute the query with pagination and wrap in an ODataResponse.
     */
    private function executeWithPagination(?int $top, ?int $skip): ODataResponse
    {
        $perPage = $top ?? $this->defaultTop ?? 15;
        $page = $skip !== null && $perPage > 0 ? (int) floor($skip / $perPage) + 1 : 1;

        $paginator = $this->builder->paginate($perPage, ['*'], 'page', $page);
        $includeCount = $this->queryOptions->count === true;

        return new ODataResponse($paginator, $includeCount, $this->responseFormat);
    }

    /**
     * Validate that all property paths used in the filter are in the allowlist.
     *
     * @return bool True if all columns are valid, false if any are invalid (and throw_on_invalid is false).
     */
    private function validateFilterColumns(Expression $expr): bool
    {
        if ($expr instanceof PropertyPath) {
            $column = implode('.', array_map(CaseConverter::toSnakeCase(...), $expr->segments));
            if (!in_array($column, $this->allowedFilters, true)) {
                if ($this->throwOnInvalid) {
                    throw InvalidQueryException::invalidFilter($column, $this->allowedFilters);
                }

                return false;
            }

            return true;
        }

        if ($expr instanceof \NovaBytes\OData\AST\Filter\BinaryExpression) {
            $leftValid = $this->validateFilterColumns($expr->left);
            $rightValid = $this->validateFilterColumns($expr->right);

            return $leftValid && $rightValid;
        }

        if ($expr instanceof \NovaBytes\OData\AST\Filter\UnaryExpression) {
            return $this->validateFilterColumns($expr->operand);
        }

        if ($expr instanceof \NovaBytes\OData\AST\Filter\FunctionCall) {
            foreach ($expr->arguments as $arg) {
                if (!$this->validateFilterColumns($arg)) {
                    return false;
                }
            }

            return true;
        }

        if ($expr instanceof \NovaBytes\OData\AST\Filter\LambdaExpression) {
            if ($expr->predicate !== null) {
                return $this->validateFilterColumns($expr->predicate);
            }

            return true;
        }

        return true;
    }

    /**
     * Convert a SelectItem to a snake_case column name.
     */
    private function selectItemToColumn(SelectItem $item): string
    {
        return implode('.', array_map(CaseConverter::toSnakeCase(...), $item->path));
    }

    /**
     * Convert an ExpandItem to a camelCase relationship name.
     */
    private function expandItemToRelation(ExpandItem $item): string
    {
        return implode('.', array_map(CaseConverter::toCamelCase(...), $item->path));
    }

    /**
     * Convert an OrderByItem to a snake_case column name.
     */
    private function orderByItemToColumn(OrderByItem $item): string
    {
        if ($item->expression instanceof PropertyPath) {
            return implode('.', array_map(CaseConverter::toSnakeCase(...), $item->expression->segments));
        }

        throw new \InvalidArgumentException('Only property paths are supported in $orderby.');
    }

    /**
     * Check if an expand relation (possibly nested with dots) is allowed.
     * 'category' is allowed if 'category' is in the list.
     * 'category.parent' is allowed if 'category.parent' is in the list.
     */
    private function isExpandAllowed(string $relation): bool
    {
        foreach ($this->allowedExpands as $allowed) {
            if ($relation === $allowed || str_starts_with($relation, $allowed . '.')) {
                return true;
            }
        }

        return false;
    }
}
