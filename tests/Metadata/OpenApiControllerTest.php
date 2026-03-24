<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Metadata;

use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OpenApiControllerTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('odata.metadata.enabled', true);
        $app['config']->set('odata.metadata.route_prefix', 'odata');
        $app['config']->set('odata.metadata.openapi', [
            'title' => 'Test API',
            'version' => '1.0.0',
            'description' => 'A test OData API.',
        ]);
        $app['config']->set('odata.entity_sets', [
            Product::class => [
                'allowedFilters' => ['name', 'price'],
                'allowedSorts' => ['name'],
                'allowedExpands' => ['category'],
                'allowedSelects' => ['id', 'name', 'price'],
            ],
        ]);
    }

    #[Test]
    public function it_returns_openapi_json(): void
    {
        $response = $this->get('/odata/openapi.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $spec = $response->json();

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('Test API', $spec['info']['title']);
        $this->assertSame('1.0.0', $spec['info']['version']);
        $this->assertSame('A test OData API.', $spec['info']['description']);
        $this->assertArrayHasKey('/odata/Products', $spec['paths']);
        $this->assertArrayHasKey('Product', $spec['components']['schemas']);
    }

    #[Test]
    public function it_includes_query_parameters(): void
    {
        $response = $this->get('/odata/openapi.json');
        $spec = $response->json();

        $parameters = $spec['paths']['/odata/Products']['get']['parameters'];
        $paramNames = array_column($parameters, 'name');

        $this->assertContains('$filter', $paramNames);
        $this->assertContains('$select', $paramNames);
        $this->assertContains('$expand', $paramNames);
        $this->assertContains('$orderby', $paramNames);
        $this->assertContains('$top', $paramNames);
        $this->assertContains('$skip', $paramNames);
        $this->assertContains('$count', $paramNames);
    }

}
