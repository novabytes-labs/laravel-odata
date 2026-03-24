<?php

declare(strict_types=1);

namespace NovaBytes\OData\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelWithMixedMethods extends Model
{
    protected $table = 'products';

    protected $guarded = [];

    /**
     * A relation method (should be discovered).
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * A method with parameters (should be skipped).
     */
    public function findByName(string $name): string
    {
        return $name;
    }

    /**
     * A method with no return type hint (should be skipped).
     */
    public function noReturnType()
    {
        return 'test';
    }

    /**
     * A method with a non-Relation return type (should be skipped).
     */
    public function formattedPrice(): string
    {
        return '$' . $this->price;
    }
}
