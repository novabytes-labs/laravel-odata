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
}
