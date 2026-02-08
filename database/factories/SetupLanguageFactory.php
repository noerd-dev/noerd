<?php

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\SetupLanguage;

class SetupLanguageFactory extends Factory
{
    protected $model = SetupLanguage::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->languageCode(),
            'name' => $this->faker->word(),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
