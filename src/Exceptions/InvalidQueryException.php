<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidQueryException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(400, $message);
    }

    public static function invalidFilter(string $filter, array $allowed): self
    {
        $list = implode(', ', $allowed);

        return new self("Filter '{$filter}' is not allowed. Allowed filters: {$list}.");
    }

    public static function invalidSort(string $sort, array $allowed): self
    {
        $list = implode(', ', $allowed);

        return new self("Sort '{$sort}' is not allowed. Allowed sorts: {$list}.");
    }

    public static function invalidExpand(string $expand, array $allowed): self
    {
        $list = implode(', ', $allowed);

        return new self("Expand '{$expand}' is not allowed. Allowed expands: {$list}.");
    }

    public static function invalidSelect(string $select, array $allowed): self
    {
        $list = implode(', ', $allowed);

        return new self("Select '{$select}' is not allowed. Allowed selects: {$list}.");
    }

    public static function expandDepthExceeded(int $depth, int $max): self
    {
        return new self("Expand depth {$depth} exceeds maximum allowed depth of {$max}.");
    }

    public static function topExceeded(int $top, int $max): self
    {
        return new self("\$top value {$top} exceeds maximum allowed value of {$max}.");
    }
}
