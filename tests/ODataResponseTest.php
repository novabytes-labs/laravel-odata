<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use NovaBytes\OData\Laravel\ODataQueryBuilder;
use NovaBytes\OData\Laravel\Response\ODataResponse;
use NovaBytes\OData\Laravel\Tests\Models\Category;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\Models\Review;
use PHPUnit\Framework\Attributes\Test;

class ODataResponseTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase();
    }

    /**
     * Seed the test database with categories, products, and reviews.
     */
    private function seedDatabase(): void
    {
        $electronics = Category::create(['name' => 'Electronics', 'is_active' => true]);
        $food = Category::create(['name' => 'Food', 'is_active' => true]);

        $laptop = Product::create(['name' => 'Laptop', 'price' => 999.99, 'description' => 'A powerful laptop', 'category_id' => $electronics->id, 'is_active' => true]);
        $phone = Product::create(['name' => 'Phone', 'price' => 499.99, 'description' => 'A smartphone', 'category_id' => $electronics->id, 'is_active' => true]);
        Product::create(['name' => 'Milk', 'price' => 2.99, 'description' => 'Fresh milk', 'category_id' => $food->id, 'is_active' => true]);
        Product::create(['name' => 'Cheese', 'price' => 5.49, 'description' => 'Aged cheese', 'category_id' => $food->id, 'is_active' => true]);
        Product::create(['name' => 'OldWidget', 'price' => 1.00, 'description' => 'Discontinued', 'category_id' => $food->id, 'is_active' => false]);

        Review::create(['product_id' => $laptop->id, 'author' => 'Alice', 'rating' => 5, 'body' => 'Great laptop!']);
        Review::create(['product_id' => $phone->id, 'author' => 'Bob', 'rating' => 4, 'body' => 'Good value']);
    }

    /**
     * Create a GET request with the given OData query string.
     */
    private function makeRequest(string $queryString): Request
    {
        return Request::create('/products?' . $queryString, 'GET', server: ['QUERY_STRING' => $queryString]);
    }

    /**
     * Build an ODataResponse from a query string.
     */
    private function buildResponse(string $queryString): ODataResponse
    {
        $request = $this->makeRequest($queryString);

        $response = ODataQueryBuilder::for(Product::class, $request)->get();

        $this->assertInstanceOf(ODataResponse::class, $response);

        return $response;
    }

    #[Test]
    public function it_returns_json_response_via_to_response(): void
    {
        $response = $this->buildResponse('$count=true&$top=2');
        $request = $this->makeRequest('$count=true&$top=2');

        $jsonResponse = $response->toResponse($request);

        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);
        $this->assertSame(200, $jsonResponse->getStatusCode());
    }

    #[Test]
    public function it_returns_collection_via_get_collection(): void
    {
        $response = $this->buildResponse('$count=true&$top=2');

        $collection = $response->getCollection();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    #[Test]
    public function it_returns_total_count(): void
    {
        $response = $this->buildResponse('$count=true&$top=2');

        $this->assertSame(5, $response->total());
    }

    #[Test]
    public function it_includes_next_link_in_odata_format(): void
    {
        $this->app['config']->set('odata.response_format', 'odata');

        $response = $this->buildResponse('$count=true&$top=2');
        $array = $response->toArray();

        $this->assertArrayHasKey('@odata.nextLink', $array);
        $nextLink = urldecode($array['@odata.nextLink']);
        $this->assertStringContainsString('$skip=', $nextLink);
        $this->assertStringContainsString('$top=', $nextLink);
    }

    #[Test]
    public function it_returns_odata_format_without_count(): void
    {
        $this->app['config']->set('odata.response_format', 'odata');

        $response = $this->buildResponse('$top=2');
        $array = $response->toArray();

        $this->assertArrayHasKey('value', $array);
        $this->assertArrayNotHasKey('@odata.count', $array);
    }
}
