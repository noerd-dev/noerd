<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Models\NoerdUser;
use Noerd\Models\UserSetting;
use Noerd\Support\Locales;

class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => NoerdUser::factory(),
            'locale' => 'en',
            // Deterministic and valid: "no user preference" is an explicit
            // state (withFormatLocale(null)), never the default.
            'format_locale' => Locales::DEFAULT,
        ];
    }

    public function forUser(NoerdUser $user): static
    {
        return $this->state(fn(array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    public function withLocale(string $locale): static
    {
        return $this->state(fn(array $attributes): array => [
            'locale' => $locale,
        ]);
    }

    public function withFormatLocale(?string $formatLocale): static
    {
        return $this->state(fn(array $attributes): array => [
            'format_locale' => $formatLocale,
        ]);
    }

    public function withSelectedTenantId(?int $tenantId): static
    {
        return $this->state(fn(array $attributes): array => [
            'selected_tenant_id' => $tenantId,
        ]);
    }
}
