<?php

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\UserSetting;

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

    public function withSelectedTenantId(?int $tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'selected_tenant_id' => $tenantId,
        ]);
    }
}
