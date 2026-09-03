<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\Tenant;
use Noerd\Support\Locales;

/**
 * @extends Factory<NoerdSettings>
 */
class NoerdSettingsFactory extends Factory
{
    protected $model = NoerdSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Deterministic: these values decide how every amount and date in the
        // tenant is written, so a formatting test must be able to rely on them.
        return [
            'tenant_id' => Tenant::factory(),
            'currency' => CurrencyHelper::DEFAULT_CURRENCY,
            'locale' => Locales::DEFAULT,
        ];
    }

    public function withCurrency(string $currency): static
    {
        return $this->state(fn(array $attributes): array => [
            'currency' => $currency,
        ]);
    }

    public function withLocale(string $locale): static
    {
        return $this->state(fn(array $attributes): array => [
            'locale' => $locale,
        ]);
    }
}
