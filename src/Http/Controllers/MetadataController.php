<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Http\Controllers;

use Illuminate\Http\Response;
use NovaBytes\OData\Laravel\Metadata\EntitySetRegistry;
use NovaBytes\OData\Metadata\CsdlGenerator;

/**
 * Serves the OData $metadata CSDL XML document.
 */
class MetadataController
{
    /**
     * Return the CSDL XML metadata document.
     */
    public function __invoke(EntitySetRegistry $registry): Response
    {
        $namespace = config('odata.namespace', 'Default');
        $entityTypes = array_values($registry->all());

        $xml = CsdlGenerator::generate($namespace, $entityTypes);

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
