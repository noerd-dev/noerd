<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\SetupCollection;
use Noerd\Models\Tenant;

class SetupCollectionFactory extends Factory
{
    protected $model = SetupCollection::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'collection_key' => fake()->unique()->slug(2),
            'name' => $this->faker->word(),
        ];
    }
}
