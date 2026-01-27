<?php

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\UserRole;

class UserRoleFactory extends Factory
{
    protected $model = UserRole::class;

    public function definition(): array
    {
        return [
            'key' => mb_strtoupper($this->faker->unique()->word),
            'name' => $this->faker->jobTitle,
            'description' => $this->faker->sentence,
            'tenant_id' => null, // Will be set by the test
        ];
    }
}
