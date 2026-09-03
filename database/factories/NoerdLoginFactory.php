<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\NoerdLogin;
use Noerd\Models\NoerdUser;

class NoerdLoginFactory extends Factory
{
    protected $model = NoerdLogin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => NoerdUser::factory(),
            'impersonated_by_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'remember' => false,
            'created_at' => now(),
        ];
    }
}
