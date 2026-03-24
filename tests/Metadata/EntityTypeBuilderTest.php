<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Metadata;

use NovaBytes\OData\Laravel\Metadata\EntityTypeBuilder;
use NovaBytes\OData\Laravel\Tests\Models\ModelWithMixedMethods;
use NovaBytes\OData\Laravel\Tests\Models\Product;
use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EntityTypeBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_entity_type_from_model(): void
    {
        $entityType = EntityTypeBuilder::build(Product::class, [
            'allowedFilters' => ['name', 'price'],
            'allowedSorts' => ['name', 'price'],
            'allowedSelects' => ['id', 'name', 'price'],
            'allowedExpands' => ['category', 'reviews'],
        ]);

        $this->assertSame('Product', $entityType->name);
        $this->assertSame('Products', $entityType->entitySetName);
        $this->assertSame('Id', $entityType->keyProperty);
    }

    #[Test]
    public function it_discovers_properties_from_schema(): void
    {
        $entityType = EntityTypeBuilder::build(Product::class, [
            'allowedFilters' => ['name', 'price'],
            'allowedSorts' => ['name'],
            'allowedSelects' => ['id', 'name'],
        ]);

        $propertyNames = array_map(fn($p) => $p->name, $entityType->properties);
        $this->assertContains('Id', $propertyNames);
        $this->assertContains('Name', $propertyNames);
        $this->assertContains('Price', $propertyNames);
        $this->assertContains('CategoryId', $propertyNames);

        $nameProperty = $this->findProperty($entityType->properties, 'Name');
        $this->assertTrue($nameProperty->filterable);
        $this->assertTrue($nameProperty->sortable);
        $this->assertTrue($nameProperty->selectable);

        $priceProperty = $this->findProperty($entityType->properties, 'Price');
        $this->assertTrue($priceProperty->filterable);
        $this->assertFalse($priceProperty->sortable);
        $this->assertFalse($priceProperty->selectable);
    }

    #[Test]
    public function it_discovers_navigation_properties(): void
    {
        $entityType = EntityTypeBuilder::build(Product::class, [
            'allowedExpands' => ['category', 'reviews'],
        ]);

        $this->assertCount(2, $entityType->navigationProperties);

        $category = $this->findNavProperty($entityType->navigationProperties, 'Category');
        $this->assertSame('Category', $category->targetEntityType);
        $this->assertFalse($category->isCollection);

        $reviews = $this->findNavProperty($entityType->navigationProperties, 'Reviews');
        $this->assertSame('Review', $reviews->targetEntityType);
        $this->assertTrue($reviews->isCollection);
    }

    #[Test]
    public function it_only_includes_allowed_expands(): void
    {
        $entityType = EntityTypeBuilder::build(Product::class, [
            'allowedExpands' => ['category'],
        ]);

        $this->assertCount(1, $entityType->navigationProperties);
        $this->assertSame('Category', $entityType->navigationProperties[0]->name);
    }

    #[Test]
    public function it_uses_custom_entity_set_name(): void
    {
        $entityType = EntityTypeBuilder::build(Product::class, [
            'entitySet' => 'AllProducts',
        ]);

        $this->assertSame('AllProducts', $entityType->entitySetName);
    }

    #[Test]
    public function it_builds_with_empty_config(): void
    {
        $entityType = EntityTypeBuilder::build(Product::class);

        $this->assertSame('Product', $entityType->name);
        $this->assertNotEmpty($entityType->properties);
        $this->assertEmpty($entityType->navigationProperties);
    }

    /**
     * Find a property by name.
     *
     * @param list<\NovaBytes\OData\Metadata\PropertyMetadata> $properties
     */
    private function findProperty(array $properties, string $name): \NovaBytes\OData\Metadata\PropertyMetadata
    {
        foreach ($properties as $property) {
            if ($property->name === $name) {
                return $property;
            }
        }

        $this->fail("Property '{$name}' not found.");
    }

    /**
     * Find a navigation property by name.
     *
     * @param list<\NovaBytes\OData\Metadata\NavigationPropertyMetadata> $navigationProperties
     */
    private function findNavProperty(array $navigationProperties, string $name): \NovaBytes\OData\Metadata\NavigationPropertyMetadata
    {
        foreach ($navigationProperties as $navProperty) {
            if ($navProperty->name === $name) {
                return $navProperty;
            }
        }

        $this->fail("Navigation property '{$name}' not found.");
    }

    /**
     * Model methods with parameters, no return type, or non-Relation return types
     * should be skipped when building navigation properties.
     */
    #[Test]
    public function it_skips_non_relation_methods_when_building_navigation_properties(): void
    {
        $entityType = EntityTypeBuilder::build(ModelWithMixedMethods::class, [
            'allowedExpands' => ['reviews', 'findByName', 'noReturnType', 'formattedPrice'],
        ]);

        $this->assertCount(1, $entityType->navigationProperties);
        $this->assertSame('Reviews', $entityType->navigationProperties[0]->name);
    }
}
