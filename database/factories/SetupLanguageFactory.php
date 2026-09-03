<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;

class SetupLanguageFactory extends Factory
{
    protected $model = SetupLanguage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->languageCode(),
            'name' => fake()->word(),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
