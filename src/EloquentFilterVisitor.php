<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel;

use Illuminate\Database\Eloquent\Builder;
use NovaBytes\OData\AST\Expression;
use NovaBytes\OData\AST\Filter\BinaryExpression;
use NovaBytes\OData\AST\Filter\BinaryOperator;
use NovaBytes\OData\AST\Filter\FunctionCall;
use NovaBytes\OData\AST\Filter\LambdaExpression;
use NovaBytes\OData\AST\Filter\LambdaOperator;
use NovaBytes\OData\AST\Filter\ListExpression;
use NovaBytes\OData\AST\Filter\Literal;
use NovaBytes\OData\AST\Filter\PropertyPath;
use NovaBytes\OData\AST\Filter\UnaryExpression;
use NovaBytes\OData\AST\Filter\UnaryOperator;
use NovaBytes\OData\Visitor\ExpressionVisitor;

class EloquentFilterVisitor implements ExpressionVisitor
{
    public function __construct(
        private Builder $builder,
        private readonly array $allowedFilters,
    ) {}

    /**
     * Apply a filter expression to the Eloquent builder.
     */
    public function apply(Expression $expr): Builder
    {
        $this->applyExpression($this->builder, $expr);

        return $this->builder;
    }

    /**
     * Apply an expression to a specific builder instance.
     * Used for nested where groups.
     */
    private function applyExpression(Builder $builder, Expression $expr): void
    {
        match (true) {
            $expr instanceof BinaryExpression => $this->applyBinary($builder, $expr),
            $expr instanceof UnaryExpression => $this->applyUnary($builder, $expr),
            $expr instanceof FunctionCall => $this->applyFunctionAsCondition($builder, $expr),
            $expr instanceof LambdaExpression => $this->applyLambda($builder, $expr),
            default => throw new \InvalidArgumentException('Unsupported expression type: ' . get_class($expr)),
        };
    }

    /**
     * Apply a binary expression (AND/OR, In, comparison, or function call comparison).
     */
    private function applyBinary(Builder $builder, BinaryExpression $expr): void
    {
        if ($expr->operator === BinaryOperator::And) {
            $builder->where(function (Builder $q) use ($expr) {
                $this->applyExpression($q, $expr->left);
                $this->applyExpression($q, $expr->right);
            });

            return;
        }

        if ($expr->operator === BinaryOperator::Or) {
            $builder->where(function (Builder $q) use ($expr) {
                $q->where(function (Builder $q2) use ($expr) {
                    $this->applyExpression($q2, $expr->left);
                });
                $q->orWhere(function (Builder $q2) use ($expr) {
                    $this->applyExpression($q2, $expr->right);
                });
            });

            return;
        }

        if ($expr->operator === BinaryOperator::In && $expr->left instanceof PropertyPath && $expr->right instanceof ListExpression) {
            $column = $this->resolveColumn($expr->left);
            $values = array_map(fn(Expression $item) => $this->resolveValue($item), $expr->right->items);
            $builder->whereIn($column, $values);

            return;
        }

        if ($expr->left instanceof PropertyPath && $expr->right instanceof Literal) {
            $column = $this->resolveColumn($expr->left);
            $value = $expr->right->value;
            $operator = $this->mapComparisonOperator($expr->operator);

            if ($value === null && $expr->operator === BinaryOperator::Eq) {
                $builder->whereNull($column);
            } elseif ($value === null && $expr->operator === BinaryOperator::Ne) {
                $builder->whereNotNull($column);
            } else {
                $builder->where($column, $operator, $value);
            }

            return;
        }

        if ($expr->left instanceof FunctionCall && $expr->right instanceof Literal) {
            $sql = $this->functionToSql($expr->left);
            $operator = $this->mapComparisonOperator($expr->operator);
            $builder->whereRaw("{$sql} {$operator} ?", [$expr->right->value]);

            return;
        }

        if ($expr->left instanceof Literal && $expr->right instanceof PropertyPath) {
            $column = $this->resolveColumn($expr->right);
            $value = $expr->left->value;
            $operator = $this->mapComparisonOperator($this->flipOperator($expr->operator));
            $builder->where($column, $operator, $value);

            return;
        }

        throw new \InvalidArgumentException(
            'Unsupported binary expression: ' . get_class($expr->left) . ' ' . $expr->operator->value . ' ' . get_class($expr->right),
        );
    }

    /**
     * Apply a unary expression (NOT) to the builder.
     */
    private function applyUnary(Builder $builder, UnaryExpression $expr): void
    {
        if ($expr->operator === UnaryOperator::Not) {
            $builder->where(function (Builder $q) use ($expr) {
                $this->applyExpression($q, $expr->operand);
            }, null, null, 'and not');

            return;
        }

        throw new \InvalidArgumentException('Unsupported unary operator in filter context: ' . $expr->operator->value);
    }

    /**
     * Apply a boolean function call as a where condition.
     * e.g. contains(Name, 'milk') used as a standalone filter.
     */
    private function applyFunctionAsCondition(Builder $builder, FunctionCall $expr): void
    {
        match ($expr->name) {
            'contains' => $this->applyLikeFunction($builder, $expr, '%', '%'),
            'startswith' => $this->applyLikeFunction($builder, $expr, '', '%'),
            'endswith' => $this->applyLikeFunction($builder, $expr, '%', ''),
            default => $builder->whereRaw($this->functionToSql($expr)),
        };
    }

    /**
     * Apply a LIKE condition for string functions (contains, startswith, endswith).
     */
    private function applyLikeFunction(Builder $builder, FunctionCall $expr, string $prefix, string $suffix): void
    {
        $column = $this->resolveColumn($expr->arguments[0]);
        $value = $this->resolveValue($expr->arguments[1]);
        $builder->where($column, 'LIKE', $prefix . $value . $suffix);
    }

    /**
     * Apply a lambda expression (any/all) as a relationship existence query.
     *
     * any() without predicate checks for at least one related record.
     * any(d: predicate) applies whereHas with the predicate condition.
     * all(d: predicate) uses whereDoesntHave with the negated predicate.
     */
    private function applyLambda(Builder $builder, LambdaExpression $expr): void
    {
        if (!$expr->collection instanceof PropertyPath) {
            throw new \InvalidArgumentException('Lambda collection must be a property path.');
        }

        $relation = $this->resolveRelation($expr->collection);

        if ($expr->operator === LambdaOperator::Any) {
            if ($expr->predicate === null) {
                $builder->has($relation);
            } else {
                $builder->whereHas($relation, function (Builder $q) use ($expr) {
                    $innerVisitor = new self($q, $this->allowedFilters);
                    $innerExpr = $this->rewriteLambdaBody($expr->predicate, $expr->variable);
                    $innerVisitor->applyExpression($q, $innerExpr);
                });
            }

            return;
        }

        if ($expr->operator === LambdaOperator::All) {
            $builder->whereDoesntHave($relation, function (Builder $q) use ($expr) {
                $negated = new UnaryExpression(UnaryOperator::Not, $expr->predicate);
                $innerVisitor = new self($q, $this->allowedFilters);
                $innerExpr = $this->rewriteLambdaBody($negated, $expr->variable);
                $innerVisitor->applyExpression($q, $innerExpr);
            });

            return;
        }
    }

    /**
     * Rewrite lambda body: strip the lambda variable prefix from property paths.
     * e.g. with variable 'd': PropertyPath(['d', 'Qty']) → PropertyPath(['Qty'])
     */
    private function rewriteLambdaBody(Expression $expr, ?string $variable): Expression
    {
        if ($variable === null) {
            return $expr;
        }

        if ($expr instanceof PropertyPath) {
            $segments = $expr->segments;
            if (!empty($segments) && $segments[0] === $variable) {
                array_shift($segments);

                return new PropertyPath($segments);
            }

            return $expr;
        }

        if ($expr instanceof BinaryExpression) {
            return new BinaryExpression(
                $this->rewriteLambdaBody($expr->left, $variable),
                $expr->operator,
                $this->rewriteLambdaBody($expr->right, $variable),
            );
        }

        if ($expr instanceof UnaryExpression) {
            return new UnaryExpression(
                $expr->operator,
                $this->rewriteLambdaBody($expr->operand, $variable),
            );
        }

        if ($expr instanceof FunctionCall) {
            $args = array_map(
                fn(Expression $arg) => $this->rewriteLambdaBody($arg, $variable),
                $expr->arguments,
            );

            return new FunctionCall($expr->name, $args);
        }

        return $expr;
    }

    /**
     * Convert a function call to a raw SQL expression.
     */
    private function functionToSql(FunctionCall $expr): string
    {
        return match ($expr->name) {
            'length' => 'CHAR_LENGTH(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'tolower' => 'LOWER(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'toupper' => 'UPPER(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'trim' => 'TRIM(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'year' => 'YEAR(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'month' => 'MONTH(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'day' => 'DAY(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'hour' => 'HOUR(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'minute' => 'MINUTE(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'second' => 'SECOND(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'round' => 'ROUND(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'floor' => 'FLOOR(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'ceiling' => 'CEILING(' . $this->resolveColumn($expr->arguments[0]) . ')',
            'concat' => 'CONCAT(' . $this->resolveColumn($expr->arguments[0]) . ', ' . $this->resolveColumn($expr->arguments[1]) . ')',
            'now' => 'NOW()',
            default => throw new \InvalidArgumentException("Unsupported function: {$expr->name}"),
        };
    }

    /**
     * Resolve a PropertyPath expression to a snake_case column name.
     *
     * Single segment paths resolve to a direct column name.
     * Multi-segment paths resolve to dot-notation for relationship columns.
     */
    private function resolveColumn(Expression $expr): string
    {
        if (!$expr instanceof PropertyPath) {
            throw new \InvalidArgumentException('Expected property path, got: ' . get_class($expr));
        }

        $segments = array_map(CaseConverter::toSnakeCase(...), $expr->segments);

        if (count($segments) === 1) {
            return $segments[0];
        }

        return implode('.', $segments);
    }

    /**
     * Resolve a PropertyPath to a camelCase relationship name.
     */
    private function resolveRelation(PropertyPath $path): string
    {
        $segments = array_map(CaseConverter::toCamelCase(...), $path->segments);

        return implode('.', $segments);
    }

    /**
     * Resolve an expression to its PHP value.
     */
    private function resolveValue(Expression $expr): mixed
    {
        if ($expr instanceof Literal) {
            return $expr->value;
        }

        if ($expr instanceof PropertyPath) {
            return $this->resolveColumn($expr);
        }

        throw new \InvalidArgumentException('Cannot resolve value from: ' . get_class($expr));
    }

    /**
     * Map an OData binary operator to its SQL equivalent.
     */
    private function mapComparisonOperator(BinaryOperator $op): string
    {
        return match ($op) {
            BinaryOperator::Eq => '=',
            BinaryOperator::Ne => '!=',
            BinaryOperator::Gt => '>',
            BinaryOperator::Ge => '>=',
            BinaryOperator::Lt => '<',
            BinaryOperator::Le => '<=',
            default => throw new \InvalidArgumentException("Cannot map operator '{$op->value}' to SQL."),
        };
    }

    /**
     * Flip a comparison operator for reversed operand order.
     */
    private function flipOperator(BinaryOperator $op): BinaryOperator
    {
        return match ($op) {
            BinaryOperator::Gt => BinaryOperator::Lt,
            BinaryOperator::Ge => BinaryOperator::Le,
            BinaryOperator::Lt => BinaryOperator::Gt,
            BinaryOperator::Le => BinaryOperator::Ge,
            default => $op,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function visitBinaryExpression(BinaryExpression $expr): mixed
    {
        $this->applyBinary($this->builder, $expr);

        return $this->builder;
    }

    /**
     * {@inheritdoc}
     */
    public function visitUnaryExpression(UnaryExpression $expr): mixed
    {
        $this->applyUnary($this->builder, $expr);

        return $this->builder;
    }

    /**
     * {@inheritdoc}
     */
    public function visitPropertyPath(PropertyPath $expr): mixed
    {
        return $this->resolveColumn($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function visitLiteral(Literal $expr): mixed
    {
        return $expr->value;
    }

    /**
     * {@inheritdoc}
     */
    public function visitFunctionCall(FunctionCall $expr): mixed
    {
        $this->applyFunctionAsCondition($this->builder, $expr);

        return $this->builder;
    }

    /**
     * {@inheritdoc}
     */
    public function visitLambdaExpression(LambdaExpression $expr): mixed
    {
        $this->applyLambda($this->builder, $expr);

        return $this->builder;
    }

    /**
     * {@inheritdoc}
     */
    public function visitListExpression(ListExpression $expr): mixed
    {
        return array_map(fn(Expression $item) => $this->resolveValue($item), $expr->items);
    }
}
