<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests;

use NovaBytes\OData\Laravel\CaseConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CaseConverterTest extends TestCase
{
    #[Test]
    public function it_converts_to_camel_case(): void
    {
        $this->assertSame('category', CaseConverter::toCamelCase('Category'));
        $this->assertSame('orderDetails', CaseConverter::toCamelCase('OrderDetails'));
        $this->assertSame('category', CaseConverter::toCamelCase('category'));
    }

    #[Test]
    public function it_converts_to_snake_case(): void
    {
        $this->assertSame('price', CaseConverter::toSnakeCase('Price'));
        $this->assertSame('category_id', CaseConverter::toSnakeCase('CategoryId'));
        $this->assertSame('first_name', CaseConverter::toSnakeCase('firstName'));
        $this->assertSame('already_snake', CaseConverter::toSnakeCase('already_snake'));
        $this->assertSame('lowercase', CaseConverter::toSnakeCase('lowercase'));
    }
}
