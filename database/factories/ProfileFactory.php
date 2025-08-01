<?php

namespace Noerd\Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Noerd\Models\Profile;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'key' => mb_strtoupper($this->faker->unique()->word),
            'name' => $this->faker->jobTitle,
            'tenant_id' => null, // Will be set by the test
        ];
    }
}
