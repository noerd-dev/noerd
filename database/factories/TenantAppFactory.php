<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noerd\Models\TenantApp;

/**
 * @extends Factory<TenantApp>
 */
class TenantAppFactory extends Factory
{
    protected $model = TenantApp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // The canonical shape: name = UPPERCASE module key, route = its lowercase
        // counterpart (see TenantApp::scopeNamedAny()).
        $key = Str::upper(fake()->unique()->lexify('ZZAPP???'));

        return [
            'title' => Str::headline(Str::lower($key)),
            'name' => $key,
            'icon' => 'heroicon:outline:rectangle-stack',
            'route' => Str::lower($key),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
