<?php

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'uuid' => Str::uuid()->toString(),
        ];
    }

    public function withApp(string $appName): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($appName): void {
            $tenantApp = TenantApp::where('name', $appName)->first();
            if ($tenantApp) {
                $tenant->tenantApps()->attach($tenantApp->id);
            }
        });
    }
}
