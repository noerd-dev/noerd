<?php

namespace Noerd\Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            // Use 'hash' for backward compatibility with existing databases
            // New projects will have 'uuid' column, but the Tenant model provides accessors
            'hash' => Str::uuid()->toString(),
        ];
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

    public function accountingModule()
    {
        $tenantApp = TenantApp::where('name', 'ACCOUNTING')->first();
        return $this->afterCreating(function (Tenant $tenant) use ($tenantApp): void {
            $tenant->tenantApps()->attach($tenantApp->id);
        });
    }
}
