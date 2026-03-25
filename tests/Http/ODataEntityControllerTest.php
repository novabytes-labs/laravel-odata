<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Http;

use NovaBytes\OData\Laravel\Tests\Models\Category;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ODataEntityControllerTest extends TestCase
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
     * {@inheritdoc}
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('odata.crud.enabled', true);
        $app['config']->set('odata.crud.route_prefix', 'api');
        $app['config']->set('odata.crud.middleware', []);
        $app['config']->set('odata.entity_sets', [
            Product::class => [
                'entitySet' => 'Products',
                'operations' => ['read', 'create', 'update', 'delete'],
                'allowedFilters' => ['name', 'price'],
                'allowedSorts' => ['name', 'price'],
                'allowedExpands' => ['category'],
                'allowedSelects' => ['id', 'name', 'price', 'description', 'category_id'],
                'allowedCreates' => ['name', 'price', 'description', 'category_id'],
                'allowedUpdates' => ['name', 'price', 'description'],
            ],
            Category::class => [
                'entitySet' => 'Categories',
                'operations' => ['read'],
                'allowedFilters' => ['name'],
                'allowedSorts' => ['name'],
                'allowedSelects' => ['id', 'name'],
            ],
        ]);
    }

    /**
     * Seed the test database.
     */
    private function seedDatabase(): void
    {
        $electronics = Category::create(['name' => 'Electronics', 'is_active' => true]);
        Product::create(['name' => 'Laptop', 'price' => 999.99, 'description' => 'A powerful laptop', 'category_id' => $electronics->id, 'is_active' => true]);
        Product::create(['name' => 'Phone', 'price' => 499.99, 'description' => 'A smartphone', 'category_id' => $electronics->id, 'is_active' => true]);
    }

    // ---- READ (GET collection) ----

    #[Test]
    public function it_registers_crud_routes(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn($r) => $r->methods()[0] . ' ' . $r->uri())
            ->toArray();

        $this->assertContains('GET api/Products', $routes);
        $this->assertContains('POST api/Products', $routes);
        $this->assertContains('GET api/Products/{key}', $routes);
        $this->assertContains('PUT api/Products/{key}', $routes);
        $this->assertContains('PATCH api/Products/{key}', $routes);
        $this->assertContains('DELETE api/Products/{key}', $routes);
    }

    #[Test]
    public function it_resolves_entity_set(): void
    {
        $resolver = app(\NovaBytes\OData\Laravel\EntitySetResolver::class);
        $definition = $resolver->resolve('Products');

        $this->assertNotNull($definition);
        $this->assertSame('Products', $definition->entitySetName);
    }

    #[Test]
    public function it_lists_entities_via_get(): void
    {
        $response = $this->getJson('/api/Products');

        $response->assertStatus(200);
        $response->assertJsonStructure(['value']);
        $this->assertCount(2, $response->json('value'));
    }

    // ---- READ (GET single entity) ----

    #[Test]
    public function it_gets_single_entity_by_key(): void
    {
        $product = Product::first();

        $response = $this->getJson("/api/Products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Laptop']);
    }

    #[Test]
    public function it_returns_404_for_missing_entity(): void
    {
        $response = $this->getJson('/api/Products/9999');

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NotFound');
    }

    // ---- CREATE (POST) ----

    #[Test]
    public function it_creates_entity_via_post(): void
    {
        $category = Category::first();

        $response = $this->postJson('/api/Products', [
            'name' => 'Tablet',
            'price' => 299.99,
            'description' => 'A new tablet',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Tablet']);
        $this->assertDatabaseHas('products', ['name' => 'Tablet']);
    }

    #[Test]
    public function it_creates_entity_with_pascal_case_keys(): void
    {
        $category = Category::first();

        $response = $this->postJson('/api/Products', [
            'Name' => 'Widget',
            'Price' => 9.99,
            'Description' => 'A widget',
            'CategoryId' => $category->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Widget', 'price' => 9.99]);
    }

    #[Test]
    public function it_rejects_disallowed_create_fields(): void
    {
        $response = $this->postJson('/api/Products', [
            'name' => 'Bad',
            'price' => 1.00,
            'is_active' => false,
            'category_id' => 1,
        ]);

        $response->assertStatus(400);
    }

    #[Test]
    public function it_returns_405_when_create_not_allowed(): void
    {
        $response = $this->postJson('/api/Categories', [
            'name' => 'New Category',
        ]);

        $response->assertStatus(405);
    }

    // ---- UPDATE (PUT - full replace) ----

    #[Test]
    public function it_updates_entity_via_put(): void
    {
        $product = Product::first();

        $response = $this->putJson("/api/Products/{$product->id}", [
            'name' => 'Updated Laptop',
            'price' => 1099.99,
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Laptop']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Laptop']);
    }

    #[Test]
    public function it_nulls_unspecified_nullable_fields_on_put(): void
    {
        $product = Product::first();

        $response = $this->putJson("/api/Products/{$product->id}", [
            'name' => 'Minimal Update',
            'price' => 100.00,
        ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertSame('Minimal Update', $product->name);
        $this->assertSame(100.00, (float) $product->price);
        $this->assertNull($product->description);
    }

    // ---- UPDATE (PATCH - partial merge) ----

    #[Test]
    public function it_partially_updates_entity_via_patch(): void
    {
        $product = Product::first();
        $originalDescription = $product->description;

        $response = $this->patchJson("/api/Products/{$product->id}", [
            'name' => 'Patched Laptop',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Patched Laptop']);

        $product->refresh();
        $this->assertSame('Patched Laptop', $product->name);
        $this->assertSame($originalDescription, $product->description);
    }

    #[Test]
    public function it_rejects_disallowed_update_fields(): void
    {
        $product = Product::first();

        $response = $this->patchJson("/api/Products/{$product->id}", [
            'name' => 'OK',
            'category_id' => 999,
        ]);

        $response->assertStatus(400);
    }

    #[Test]
    public function it_returns_404_on_update_missing_entity(): void
    {
        $response = $this->putJson('/api/Products/9999', [
            'name' => 'Ghost',
        ]);

        $response->assertStatus(404);
    }

    // ---- DELETE ----

    #[Test]
    public function it_deletes_entity(): void
    {
        $product = Product::first();

        $response = $this->deleteJson("/api/Products/{$product->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    #[Test]
    public function it_returns_404_on_delete_missing_entity(): void
    {
        $response = $this->deleteJson('/api/Products/9999');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_405_when_delete_not_allowed(): void
    {
        $category = Category::first();

        $response = $this->deleteJson("/api/Categories/{$category->id}");

        $response->assertStatus(405);
    }

    // ---- Non-existent entity set ----

    #[Test]
    public function it_returns_404_for_unknown_entity_set(): void
    {
        $response = $this->getJson('/api/Unknown');

        $response->assertStatus(404);
    }
}
