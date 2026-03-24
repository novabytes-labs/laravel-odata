<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Metadata;

use NovaBytes\OData\Laravel\Metadata\EntitySetRegistry;
use NovaBytes\OData\Laravel\Tests\Models\Category;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EntitySetRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_and_retrieves_entity_types(): void
    {
        $registry = new EntitySetRegistry();
        $registry->register(Product::class, [
            'allowedFilters' => ['name'],
        ]);

        $entityType = $registry->get(Product::class);

        $this->assertNotNull($entityType);
        $this->assertSame('Product', $entityType->name);
    }

    #[Test]
    public function it_returns_null_for_unregistered_models(): void
    {
        $registry = new EntitySetRegistry();

        $this->assertNull($registry->get('App\\Models\\Unknown'));
    }

    #[Test]
    public function it_returns_all_registered_entity_types(): void
    {
        $registry = new EntitySetRegistry();
        $registry->register(Product::class, []);
        $registry->register(Category::class, []);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey(Product::class, $all);
        $this->assertArrayHasKey(Category::class, $all);
    }

    #[Test]
    public function it_caches_resolved_entity_types_on_repeated_calls(): void
    {
        $registry = new EntitySetRegistry();
        $registry->register(Product::class, [
            'allowedFilters' => ['name'],
        ]);

        $first = $registry->get(Product::class);
        $second = $registry->get(Product::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_re_resolves_after_new_registration(): void
    {
        $registry = new EntitySetRegistry();
        $registry->register(Product::class, []);

        $allBefore = $registry->all();
        $this->assertCount(1, $allBefore);

        $registry->register(Category::class, []);

        $allAfter = $registry->all();
        $this->assertCount(2, $allAfter);
    }
}
