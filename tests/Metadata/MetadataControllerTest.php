<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Metadata;

use NovaBytes\OData\Laravel\Tests\Models\Category;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MetadataControllerTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('odata.metadata.enabled', true);
        $app['config']->set('odata.metadata.route_prefix', 'odata');
        $app['config']->set('odata.namespace', 'TestApp');
        $app['config']->set('odata.entity_sets', [
            Product::class => [
                'allowedFilters' => ['name', 'price'],
                'allowedSorts' => ['name', 'price'],
                'allowedExpands' => ['category', 'reviews'],
                'allowedSelects' => ['id', 'name', 'price'],
            ],
            Category::class => [
                'allowedFilters' => ['name'],
                'allowedExpands' => ['products'],
            ],
        ]);
    }

    #[Test]
    public function it_returns_csdl_xml(): void
    {
        $response = $this->get('/odata/$metadata');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = $response->getContent();
        $this->assertStringContainsString('Version="4.0"', $xml);
        $this->assertStringContainsString('Namespace="TestApp"', $xml);
        $this->assertStringContainsString('EntityType Name="Product"', $xml);
        $this->assertStringContainsString('EntityType Name="Category"', $xml);
        $this->assertStringContainsString('EntitySet Name="Products"', $xml);
        $this->assertStringContainsString('EntitySet Name="Categories"', $xml);
        $this->assertStringContainsString('NavigationProperty Name="Category"', $xml);
        $this->assertStringContainsString('NavigationProperty Name="Reviews"', $xml);
    }

}
