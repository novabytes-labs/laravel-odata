<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests;

use NovaBytes\OData\AST\Filter\BinaryExpression;
use NovaBytes\OData\AST\Filter\BinaryOperator;
use NovaBytes\OData\AST\Filter\FunctionCall;
use NovaBytes\OData\AST\Filter\LambdaExpression;
use NovaBytes\OData\AST\Filter\LambdaOperator;
use NovaBytes\OData\AST\Filter\ListExpression;
use NovaBytes\OData\AST\Filter\Literal;
use NovaBytes\OData\AST\Filter\LiteralType;
use NovaBytes\OData\AST\Filter\PropertyPath;
use NovaBytes\OData\AST\Filter\UnaryExpression;
use NovaBytes\OData\AST\Filter\UnaryOperator;
use NovaBytes\OData\Laravel\EloquentFilterVisitor;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use PHPUnit\Framework\Attributes\Test;

class EloquentFilterVisitorTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create a visitor with a fresh Product query builder.
     */
    private function makeVisitor(): EloquentFilterVisitor
    {
        return new EloquentFilterVisitor(Product::query(), []);
    }

    #[Test]
    public function it_visits_binary_expression(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new PropertyPath(['name']),
            BinaryOperator::Eq,
            new Literal('Laptop', LiteralType::String),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    #[Test]
    public function it_visits_unary_expression(): void
    {
        $visitor = $this->makeVisitor();

        $inner = new BinaryExpression(
            new PropertyPath(['name']),
            BinaryOperator::Eq,
            new Literal('Laptop', LiteralType::String),
        );
        $expr = new UnaryExpression(UnaryOperator::Not, $inner);

        $result = $visitor->visitUnaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    #[Test]
    public function it_visits_property_path(): void
    {
        $visitor = $this->makeVisitor();

        $result = $visitor->visitPropertyPath(new PropertyPath(['CategoryId']));

        $this->assertSame('category_id', $result);
    }

    #[Test]
    public function it_visits_literal(): void
    {
        $visitor = $this->makeVisitor();

        $this->assertSame('hello', $visitor->visitLiteral(new Literal('hello', LiteralType::String)));
        $this->assertSame(42, $visitor->visitLiteral(new Literal(42, LiteralType::Integer)));
        $this->assertNull($visitor->visitLiteral(new Literal(null, LiteralType::Null)));
    }

    #[Test]
    public function it_visits_function_call(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new FunctionCall('contains', [
            new PropertyPath(['name']),
            new Literal('test', LiteralType::String),
        ]);

        $result = $visitor->visitFunctionCall($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    #[Test]
    public function it_visits_list_expression(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new ListExpression([
            new Literal('a', LiteralType::String),
            new Literal('b', LiteralType::String),
            new Literal('c', LiteralType::String),
        ]);

        $result = $visitor->visitListExpression($expr);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function it_visits_lambda_expression(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new LambdaExpression(
            new PropertyPath(['reviews']),
            LambdaOperator::Any,
            null,
            null,
        );

        $result = $visitor->visitLambdaExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    #[Test]
    public function it_resolves_multi_segment_property_path(): void
    {
        $visitor = $this->makeVisitor();

        $result = $visitor->visitPropertyPath(new PropertyPath(['Category', 'Name']));

        $this->assertSame('category.name', $result);
    }

    #[Test]
    public function it_applies_function_compared_to_literal_via_function_to_sql(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new FunctionCall('length', [new PropertyPath(['name'])]),
            BinaryOperator::Gt,
            new Literal(3, LiteralType::Integer),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function functionToSqlProvider(): array
    {
        return [
            'tolower' => ['tolower', 'LOWER(name)'],
            'toupper' => ['toupper', 'UPPER(name)'],
            'trim' => ['trim', 'TRIM(name)'],
            'year' => ['year', 'YEAR(name)'],
            'month' => ['month', 'MONTH(name)'],
            'day' => ['day', 'DAY(name)'],
            'hour' => ['hour', 'HOUR(name)'],
            'minute' => ['minute', 'MINUTE(name)'],
            'second' => ['second', 'SECOND(name)'],
            'round' => ['round', 'ROUND(name)'],
            'floor' => ['floor', 'FLOOR(name)'],
            'ceiling' => ['ceiling', 'CEILING(name)'],
        ];
    }

    /**
     * Test that each SQL function generates the correct whereRaw clause.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('functionToSqlProvider')]
    public function it_applies_sql_function_compared_to_literal(string $functionName, string $expectedSql): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new FunctionCall($functionName, [new PropertyPath(['name'])]),
            BinaryOperator::Gt,
            new Literal(3, LiteralType::Integer),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString($expectedSql, $result->toRawSql());
    }

    #[Test]
    public function it_applies_concat_function_to_sql(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new FunctionCall('concat', [
                new PropertyPath(['name']),
                new PropertyPath(['description']),
            ]),
            BinaryOperator::Eq,
            new Literal('test', LiteralType::String),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString('CONCAT(name, description)', $result->toRawSql());
    }

    #[Test]
    public function it_applies_now_function_as_standalone_condition(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new FunctionCall('now', []);

        $result = $visitor->visitFunctionCall($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString('NOW()', $result->toRawSql());
    }

    #[Test]
    public function it_throws_on_unsupported_function(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new FunctionCall('unsupported_func', [new PropertyPath(['name'])]),
            BinaryOperator::Eq,
            new Literal('test', LiteralType::String),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported function: unsupported_func');

        $visitor->visitBinaryExpression($expr);
    }

    #[Test]
    public function it_throws_on_unsupported_expression_type(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new ListExpression([
            new Literal('a', LiteralType::String),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported expression type');

        $visitor->apply($expr);
    }

    #[Test]
    public function it_throws_on_unsupported_binary_expression_combination(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new Literal('a', LiteralType::String),
            BinaryOperator::Eq,
            new Literal('b', LiteralType::String),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported binary expression');

        $visitor->visitBinaryExpression($expr);
    }

    #[Test]
    public function it_throws_on_resolve_column_with_non_property_path(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new FunctionCall('length', [new Literal('test', LiteralType::String)]),
            BinaryOperator::Eq,
            new Literal(4, LiteralType::Integer),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected property path');

        $visitor->visitBinaryExpression($expr);
    }

    #[Test]
    public function it_throws_on_unsupported_comparison_operator(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new PropertyPath(['name']),
            BinaryOperator::In,
            new Literal('test', LiteralType::String),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot map operator');

        $visitor->visitBinaryExpression($expr);
    }

    #[Test]
    public function it_throws_on_lambda_with_non_property_path_collection(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new LambdaExpression(
            new Literal('not_a_path', LiteralType::String),
            LambdaOperator::Any,
            null,
            null,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lambda collection must be a property path');

        $visitor->visitLambdaExpression($expr);
    }

    #[Test]
    public function it_resolves_value_from_property_path(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new ListExpression([
            new PropertyPath(['CategoryId']),
        ]);

        $result = $visitor->visitListExpression($expr);

        $this->assertSame(['category_id'], $result);
    }

    #[Test]
    public function it_throws_on_resolve_value_with_unsupported_expression(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new ListExpression([
            new FunctionCall('length', [new PropertyPath(['name'])]),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot resolve value from');

        $visitor->visitListExpression($expr);
    }

    #[Test]
    public function it_rewrites_lambda_body_with_function_call(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new LambdaExpression(
            new PropertyPath(['reviews']),
            LambdaOperator::Any,
            'r',
            new FunctionCall('contains', [
                new PropertyPath(['r', 'Body']),
                new Literal('great', LiteralType::String),
            ]),
        );

        $result = $visitor->visitLambdaExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    #[Test]
    public function it_flips_operators_for_literal_on_left_with_ge(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new Literal(100, LiteralType::Integer),
            BinaryOperator::Ge,
            new PropertyPath(['price']),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString('<=', $result->toRawSql());
    }

    #[Test]
    public function it_flips_operators_for_literal_on_left_with_gt(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new Literal(100, LiteralType::Integer),
            BinaryOperator::Gt,
            new PropertyPath(['price']),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString('<', $result->toRawSql());
    }

    #[Test]
    public function it_flips_operators_for_literal_on_left_with_le(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new Literal(100, LiteralType::Integer),
            BinaryOperator::Le,
            new PropertyPath(['price']),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString('>=', $result->toRawSql());
    }

    #[Test]
    public function it_keeps_eq_operator_when_flipping(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new BinaryExpression(
            new Literal('Laptop', LiteralType::String),
            BinaryOperator::Eq,
            new PropertyPath(['name']),
        );

        $result = $visitor->visitBinaryExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
        $this->assertStringContainsString('=', $result->toRawSql());
    }

    /**
     * When a lambda body contains a PropertyPath that doesn't start with the variable,
     * it should be returned unchanged.
     */
    #[Test]
    public function it_rewrites_lambda_body_preserving_non_variable_property_path(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new LambdaExpression(
            new PropertyPath(['reviews']),
            LambdaOperator::Any,
            'r',
            new BinaryExpression(
                new PropertyPath(['r', 'Rating']),
                BinaryOperator::Gt,
                new Literal(3, LiteralType::Integer),
            ),
        );

        $result = $visitor->visitLambdaExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }

    /**
     * When rewriteLambdaBody encounters a Literal expression (not PropertyPath, Binary, Unary, or FunctionCall),
     * it should return the expression unchanged.
     */
    #[Test]
    public function it_rewrites_lambda_body_returning_literal_unchanged(): void
    {
        $visitor = $this->makeVisitor();

        $expr = new LambdaExpression(
            new PropertyPath(['reviews']),
            LambdaOperator::Any,
            'r',
            new BinaryExpression(
                new PropertyPath(['r', 'Rating']),
                BinaryOperator::In,
                new ListExpression([
                    new Literal(4, LiteralType::Integer),
                    new Literal(5, LiteralType::Integer),
                ]),
            ),
        );

        $result = $visitor->visitLambdaExpression($expr);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $result);
    }
}
