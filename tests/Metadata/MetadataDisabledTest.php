<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Metadata;

use NovaBytes\OData\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MetadataDisabledTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('odata.metadata.enabled', false);
    }

    #[Test]
    public function it_does_not_register_metadata_route_when_disabled(): void
    {
        $response = $this->get('/odata/$metadata');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_does_not_register_openapi_route_when_disabled(): void
    {
        $response = $this->get('/odata/openapi.json');

        $response->assertStatus(404);
    }
}
