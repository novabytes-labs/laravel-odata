<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Metadata;

use Illuminate\Http\Request;
use NovaBytes\OData\Laravel\Exceptions\InvalidQueryException;
use NovaBytes\OData\Laravel\ODataQueryBuilder;
use NovaBytes\OData\Laravel\Tests\Models\Category;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ODataQueryBuilderConfigTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        Category::create(['name' => 'Electronics', 'is_active' => true]);
        Product::create([
            'name' => 'Laptop',
            'price' => 999.99,
            'description' => 'A laptop',
            'category_id' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('odata.entity_sets', [
            Product::class => [
                'allowedFilters' => ['name', 'price'],
                'allowedSorts' => ['name', 'price'],
                'allowedExpands' => ['category'],
                'allowedSelects' => ['id', 'name', 'price'],
            ],
        ]);
    }

    #[Test]
    public function it_uses_config_allowlists_when_not_explicitly_set(): void
    {
        $request = $this->makeRequest('$filter=Name eq \'Laptop\'');

        $results = ODataQueryBuilder::for(Product::class, $request)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Laptop', $results->first()->name);
    }

    #[Test]
    public function it_rejects_filters_not_in_config_allowlist(): void
    {
        $this->expectException(InvalidQueryException::class);

        $request = $this->makeRequest('$filter=Description eq \'test\'');

        ODataQueryBuilder::for(Product::class, $request)->get();
    }

    #[Test]
    public function it_allows_explicit_overrides_to_take_precedence(): void
    {
        $request = $this->makeRequest('$filter=Name eq \'Laptop\'');

        $results = ODataQueryBuilder::for(Product::class, $request)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_falls_back_to_no_restrictions_when_not_in_config(): void
    {
        $request = $this->makeRequest('$filter=Name eq \'Electronics\'');

        $results = ODataQueryBuilder::for(Category::class, $request)->get();

        $this->assertCount(1, $results);
    }

    /**
     * Create a GET request with the given OData query string.
     */
    private function makeRequest(string $queryString): Request
    {
        return Request::create('/test?' . $queryString, 'GET', server: ['QUERY_STRING' => $queryString]);
    }
}
