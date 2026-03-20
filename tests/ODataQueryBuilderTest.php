<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests;

use Illuminate\Http\Request;
use NovaBytes\OData\Laravel\Exceptions\InvalidQueryException;
use NovaBytes\OData\Laravel\ODataQueryBuilder;
use NovaBytes\OData\Laravel\Tests\Models\Category;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\Models\Review;
use PHPUnit\Framework\Attributes\Test;

class ODataQueryBuilderTest extends TestCase
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
        $inactive = Category::create(['name' => 'Discontinued', 'is_active' => false]);

        $laptop = Product::create(['name' => 'Laptop', 'price' => 999.99, 'description' => 'A powerful laptop', 'category_id' => $electronics->id, 'is_active' => true]);
        $phone = Product::create(['name' => 'Phone', 'price' => 499.99, 'description' => 'A smartphone', 'category_id' => $electronics->id, 'is_active' => true]);
        $milk = Product::create(['name' => 'Milk', 'price' => 2.99, 'description' => 'Fresh milk', 'category_id' => $food->id, 'is_active' => true]);
        $cheese = Product::create(['name' => 'Cheese', 'price' => 5.49, 'description' => 'Aged cheese', 'category_id' => $food->id, 'is_active' => true]);
        $old = Product::create(['name' => 'OldWidget', 'price' => 1.00, 'description' => 'Discontinued', 'category_id' => $inactive->id, 'is_active' => false]);

        Review::create(['product_id' => $laptop->id, 'author' => 'Alice', 'rating' => 5, 'body' => 'Great laptop!']);
        Review::create(['product_id' => $laptop->id, 'author' => 'Bob', 'rating' => 4, 'body' => 'Good value']);
        Review::create(['product_id' => $phone->id, 'author' => 'Charlie', 'rating' => 3, 'body' => 'Decent phone']);
        Review::create(['product_id' => $milk->id, 'author' => 'Alice', 'rating' => 5, 'body' => 'Fresh!']);
    }

    /**
     * Create a GET request with the given OData query string.
     */
    private function makeRequest(string $queryString): Request
    {
        return Request::create('/products?' . $queryString, 'GET', server: ['QUERY_STRING' => $queryString]);
    }

    #[Test]
    public function it_filters_by_equality(): void
    {
        $request = $this->makeRequest('$filter=Name eq \'Milk\'');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name', 'price')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Milk', $results->first()->name);
    }

    #[Test]
    public function it_filters_by_greater_than(): void
    {
        $request = $this->makeRequest('$filter=Price gt 100');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name', 'price')
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * Expects Milk (2.99) and Cheese (5.49).
     */
    #[Test]
    public function it_filters_with_and(): void
    {
        $request = $this->makeRequest('$filter=Price gt 1 and Price lt 10');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_with_or(): void
    {
        $request = $this->makeRequest('$filter=Name eq \'Milk\' or Name eq \'Cheese\'');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_with_contains(): void
    {
        $request = $this->makeRequest('$filter=contains(Name,\'op\')');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Laptop', $results->first()->name);
    }

    #[Test]
    public function it_filters_with_startswith(): void
    {
        $request = $this->makeRequest('$filter=startswith(Name,\'Ch\')');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Cheese', $results->first()->name);
    }

    /**
     * All seeded products have descriptions, so filtering for null returns none.
     */
    #[Test]
    public function it_filters_null(): void
    {
        $request = $this->makeRequest('$filter=Description eq null');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('description')
            ->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_filters_boolean(): void
    {
        $request = $this->makeRequest('$filter=IsActive eq false');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('is_active')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('OldWidget', $results->first()->name);
    }

    #[Test]
    public function it_filters_with_in_operator(): void
    {
        $request = $this->makeRequest('$filter=Name in (\'Milk\',\'Cheese\',\'Butter\')');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * Description should not be loaded when only Name and Price are selected.
     */
    #[Test]
    public function it_selects_specific_columns(): void
    {
        $request = $this->makeRequest('$select=Name,Price');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSelects('id', 'name', 'price')
            ->get();

        $first = $results->first();
        $this->assertNotNull($first->name);
        $this->assertNotNull($first->price);
        $this->assertNull($first->description);
    }

    #[Test]
    public function it_always_includes_primary_key_in_select(): void
    {
        $request = $this->makeRequest('$select=Name');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSelects('id', 'name')
            ->get();

        $this->assertNotNull($results->first()->id);
    }

    /**
     * Wildcard means all columns, so description should also be loaded.
     */
    #[Test]
    public function it_handles_wildcard_select(): void
    {
        $request = $this->makeRequest('$select=*');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSelects('id', 'name', 'price')
            ->get();

        $this->assertNotNull($results->first()->description);
    }

    #[Test]
    public function it_expands_relationships(): void
    {
        $request = $this->makeRequest('$expand=Category');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('category')
            ->get();

        $this->assertTrue($results->first()->relationLoaded('category'));
        $this->assertNotNull($results->first()->category);
    }

    #[Test]
    public function it_expands_multiple_relationships(): void
    {
        $request = $this->makeRequest('$expand=Category,Reviews');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('category', 'reviews')
            ->get();

        $this->assertTrue($results->first()->relationLoaded('category'));
        $this->assertTrue($results->first()->relationLoaded('reviews'));
    }

    /**
     * Only the 5-star review should be loaded for the Laptop.
     */
    #[Test]
    public function it_expands_with_nested_filter(): void
    {
        $request = $this->makeRequest('$expand=Reviews($filter=Rating gt 4)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('reviews')
            ->allowedFilters('rating')
            ->get();

        $laptop = $results->firstWhere('name', 'Laptop');
        $this->assertTrue($laptop->relationLoaded('reviews'));
        $this->assertCount(1, $laptop->reviews);
        $this->assertSame(5, $laptop->reviews->first()->rating);
    }

    #[Test]
    public function it_expands_with_nested_top(): void
    {
        $request = $this->makeRequest('$expand=Reviews($top=1)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('reviews')
            ->get();

        $laptop = $results->firstWhere('name', 'Laptop');
        $this->assertCount(1, $laptop->reviews);
    }

    #[Test]
    public function it_orders_ascending(): void
    {
        $request = $this->makeRequest('$orderby=Name asc');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSorts('name', 'price')
            ->get();

        $this->assertSame('Cheese', $results->first()->name);
    }

    #[Test]
    public function it_orders_descending(): void
    {
        $request = $this->makeRequest('$orderby=Price desc');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSorts('name', 'price')
            ->get();

        $this->assertSame('Laptop', $results->first()->name);
    }

    /**
     * Active products appear first, ordered by price ascending within each group.
     */
    #[Test]
    public function it_orders_by_multiple_columns(): void
    {
        $request = $this->makeRequest('$orderby=IsActive desc,Price asc');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSorts('is_active', 'price')
            ->get();

        $this->assertTrue((bool) $results->first()->is_active);
    }

    #[Test]
    public function it_applies_top(): void
    {
        $request = $this->makeRequest('$top=2');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * Skips first 2 (Cheese, Laptop), should start with Milk.
     */
    #[Test]
    public function it_applies_skip(): void
    {
        $request = $this->makeRequest('$orderby=Name asc&$skip=2&$top=2');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSorts('name')
            ->get();

        $this->assertCount(2, $results);
        $this->assertSame('Milk', $results->first()->name);
    }

    #[Test]
    public function it_returns_odata_response_with_count(): void
    {
        $request = $this->makeRequest('$count=true&$top=2');

        $response = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $array = $response->toArray();
        $this->assertSame(5, $array['meta']['total']);
        $this->assertCount(2, $array['data']);
    }

    #[Test]
    public function it_converts_pascal_case_filter_properties(): void
    {
        $request = $this->makeRequest('$filter=IsActive eq true');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('is_active')
            ->get();

        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_converts_pascal_case_select_properties(): void
    {
        $request = $this->makeRequest('$select=Name,CategoryId');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSelects('id', 'name', 'category_id')
            ->get();

        $this->assertNotNull($results->first()->name);
        $this->assertNotNull($results->first()->category_id);
    }

    #[Test]
    public function it_converts_pascal_case_orderby(): void
    {
        $request = $this->makeRequest('$orderby=CreatedAt desc');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedSorts('created_at')
            ->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_converts_pascal_case_expand(): void
    {
        $request = $this->makeRequest('$expand=Category');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('category')
            ->get();

        $this->assertTrue($results->first()->relationLoaded('category'));
    }

    #[Test]
    public function it_throws_on_disallowed_filter(): void
    {
        $request = $this->makeRequest('$filter=Description eq \'secret\'');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Filter \'description\' is not allowed');

        ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name', 'price')
            ->get();
    }

    #[Test]
    public function it_throws_on_disallowed_sort(): void
    {
        $request = $this->makeRequest('$orderby=Description asc');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Sort \'description\' is not allowed');

        ODataQueryBuilder::for(Product::class, $request)
            ->allowedSorts('name', 'price')
            ->get();
    }

    #[Test]
    public function it_throws_on_disallowed_expand(): void
    {
        $request = $this->makeRequest('$expand=Reviews');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Expand \'reviews\' is not allowed');

        ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('category')
            ->get();
    }

    #[Test]
    public function it_throws_on_disallowed_select(): void
    {
        $request = $this->makeRequest('$select=Description');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Select \'description\' is not allowed');

        ODataQueryBuilder::for(Product::class, $request)
            ->allowedSelects('id', 'name', 'price')
            ->get();
    }

    #[Test]
    public function it_throws_on_expand_depth_exceeded(): void
    {
        $this->app['config']->set('odata.max_expand_depth', 1);

        $request = $this->makeRequest('$expand=Category/Products');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('Expand depth 2 exceeds maximum');

        ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('category', 'category.products')
            ->get();
    }

    #[Test]
    public function it_throws_on_top_exceeded(): void
    {
        $this->app['config']->set('odata.max_top', 10);

        $request = $this->makeRequest('$top=100');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('$top value 100 exceeds maximum');

        ODataQueryBuilder::for(Product::class, $request)
            ->get();
    }

    /**
     * Invalid options should be silently ignored instead of throwing.
     */
    #[Test]
    public function it_silently_ignores_when_configured(): void
    {
        $this->app['config']->set('odata.throw_on_invalid', false);

        $request = $this->makeRequest('$filter=Description eq \'secret\'&$orderby=Description asc&$select=Description');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->allowedSelects('id', 'name')
            ->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_handles_combined_query(): void
    {
        $request = $this->makeRequest(
            '$filter=Price gt 2'
            . '&$select=Name,Price'
            . '&$expand=Category'
            . '&$orderby=Price desc'
            . '&$top=3'
        );

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->allowedSelects('id', 'name', 'price', 'category_id')
            ->allowedExpands('category')
            ->allowedSorts('price')
            ->get();

        $this->assertCount(3, $results);
        $this->assertSame('Laptop', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('category'));
    }

    #[Test]
    public function it_returns_builder_for_further_customization(): void
    {
        $request = $this->makeRequest('$filter=Price gt 100');

        $builder = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->toBuilder();

        $results = $builder->where('is_active', true)->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_returns_odata_format_when_configured(): void
    {
        $this->app['config']->set('odata.response_format', 'odata');

        $request = $this->makeRequest('$count=true&$top=2');

        $response = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $array = $response->toArray();
        $this->assertArrayHasKey('@odata.count', $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertSame(5, $array['@odata.count']);
        $this->assertCount(2, $array['value']);
    }

    #[Test]
    public function it_allows_everything_when_no_allowlist_set(): void
    {
        $request = $this->makeRequest('$filter=Price gt 100&$orderby=Name asc&$select=Name');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_with_endswith(): void
    {
        $request = $this->makeRequest('$filter=endswith(Name,\'se\')');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Cheese', $results->first()->name);
    }

    #[Test]
    public function it_filters_with_not_operator(): void
    {
        $request = $this->makeRequest('$filter=not contains(Name,\'Milk\')');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(4, $results);
    }

    /**
     * Products with at least one review: Laptop (2), Phone (1), Milk (1).
     */
    #[Test]
    public function it_filters_with_lambda_any_without_predicate(): void
    {
        $request = $this->makeRequest('$filter=Reviews/any()');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $this->assertCount(3, $results);
    }

    /**
     * Products with at least one review rated above 4: Laptop (5), Milk (5).
     */
    #[Test]
    public function it_filters_with_lambda_any_with_predicate(): void
    {
        $request = $this->makeRequest('$filter=Reviews/any(r: r/Rating gt 4)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('rating', 'r.rating')
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * Products where ALL reviews have rating > 3.
     * Laptop (5,4), Milk (5), Cheese (none), OldWidget (none) match.
     * Phone (3) does not — review rated 3 is not > 3.
     */
    #[Test]
    public function it_filters_with_lambda_all(): void
    {
        $request = $this->makeRequest('$filter=Reviews/all(r: r/Rating gt 3)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('rating', 'r.rating')
            ->get();

        $this->assertCount(4, $results);
        $this->assertFalse($results->contains('name', 'Phone'));
    }

    /**
     * Literal on the left side: 100 lt Price is equivalent to Price gt 100.
     */
    #[Test]
    public function it_filters_with_literal_on_left_side(): void
    {
        $request = $this->makeRequest('$filter=100 lt Price');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_with_function_compared_to_literal(): void
    {
        $request = $this->makeRequest('$filter=tolower(Name) eq \'milk\'');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Milk', $results->first()->name);
    }

    #[Test]
    public function it_filters_with_ne_operator(): void
    {
        $request = $this->makeRequest('$filter=Name ne \'Milk\'');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_filters_with_ge_operator(): void
    {
        $request = $this->makeRequest('$filter=Price ge 499.99');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_with_le_operator(): void
    {
        $request = $this->makeRequest('$filter=Price le 5.49');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_filters_with_lt_operator(): void
    {
        $request = $this->makeRequest('$filter=Price lt 5');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('price')
            ->get();

        $this->assertCount(2, $results);
    }

    /**
     * All seeded products have descriptions, so ne null returns all.
     */
    #[Test]
    public function it_filters_ne_null(): void
    {
        $request = $this->makeRequest('$filter=Description ne null');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('description')
            ->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_expands_with_nested_select(): void
    {
        $request = $this->makeRequest('$expand=Reviews($select=Id,ProductId,Author,Rating)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('reviews')
            ->get();

        $laptop = $results->firstWhere('name', 'Laptop');
        $this->assertTrue($laptop->relationLoaded('reviews'));
        $this->assertNotEmpty($laptop->reviews);
        $this->assertNotNull($laptop->reviews->first()->author);
        $this->assertNotNull($laptop->reviews->first()->rating);
        $this->assertNull($laptop->reviews->first()->body);
    }

    #[Test]
    public function it_expands_with_nested_orderby(): void
    {
        $request = $this->makeRequest('$expand=Reviews($orderby=Rating desc)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('reviews')
            ->allowedSorts('rating')
            ->get();

        $laptop = $results->firstWhere('name', 'Laptop');
        $this->assertSame(5, $laptop->reviews->first()->rating);
        $this->assertSame(4, $laptop->reviews->last()->rating);
    }

    #[Test]
    public function it_expands_with_nested_skip(): void
    {
        $request = $this->makeRequest('$expand=Reviews($orderby=Rating asc;$skip=1;$top=10)');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('reviews')
            ->allowedSorts('rating')
            ->get();

        $laptop = $results->firstWhere('name', 'Laptop');
        $this->assertCount(1, $laptop->reviews);
    }

    #[Test]
    public function it_skips_wildcard_expand(): void
    {
        $request = $this->makeRequest('$expand=*');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $this->assertCount(5, $results);
        $this->assertFalse($results->first()->relationLoaded('category'));
    }

    #[Test]
    public function it_applies_default_top_from_config(): void
    {
        $this->app['config']->set('odata.default_top', 3);

        $request = $this->makeRequest('');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_caps_top_silently_when_throw_disabled(): void
    {
        $this->app['config']->set('odata.max_top', 2);
        $this->app['config']->set('odata.throw_on_invalid', false);

        $request = $this->makeRequest('$top=100');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_accepts_builder_instance(): void
    {
        $request = $this->makeRequest('$top=10');

        $results = ODataQueryBuilder::for(Product::query()->where('is_active', true), $request)
            ->get();

        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_silently_ignores_invalid_expand(): void
    {
        $this->app['config']->set('odata.throw_on_invalid', false);

        $request = $this->makeRequest('$expand=NonExistent');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedExpands('category')
            ->get();

        $this->assertCount(5, $results);
    }
}
