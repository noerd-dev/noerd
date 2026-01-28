<?php

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\Profile;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'key' => 'TEST_' . $this->faker->unique()->numberBetween(1, 9999),
            'name' => 'Test Profile ' . $this->faker->unique()->numberBetween(1, 9999),
            'tenant_id' => null, // Will be set by the test
        ];
    }
}
