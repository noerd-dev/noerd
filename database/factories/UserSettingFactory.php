<?php

namespace Noerd\Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Noerd\Models\UserSetting;

class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    public function definition(): array
    {
        return [
            'locale' => 'en',
        ];
    }

    public function withLocale(string $locale): static
    {
        return $this->state(fn(array $attributes) => [
            'locale' => $locale,
        ]);
    }

    public function withSelectedApp(string $app): static
    {
        return $this->state(fn(array $attributes) => [
            'selected_app' => $app,
        ]);
    }
}
