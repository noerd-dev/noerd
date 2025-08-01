<?php

namespace Noerd\Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Nywerk\Liefertool\Models\Coredata;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'hash' => Str::uuid()->toString(),
            'from_email' => $this->faker->email,
            'reply_email' => $this->faker->email,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($client): void {
            Coredata::factory()->create(
                [
                    'tenant_id' => $client->id,
                ],
            );
        });
    }

    public function canteenModule()
    {
        $tenantApp = TenantApp::where('name', 'CANTEEN')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }

    public function deliveryModule()
    {
        $tenantApp = TenantApp::where('name', 'DELIVERY')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }

    public function contentModule()
    {
        $tenantApp = TenantApp::where('name', 'CMS')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }

    public function pdmModule()
    {
        $tenantApp = TenantApp::where('name', 'PDM')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }

    public function storeModule()
    {
        $tenantApp = TenantApp::where('name', 'STORE')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }

    public function restaurantModule()
    {
        $tenantApp = TenantApp::where('name', 'RESTAURANT')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }
}
