<?php

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Profile;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withExampleTenant(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->create();

            $user->tenants()->attach($restaurant->id);
            TenantHelper::setSelectedTenantId($restaurant->id);
        });
    }

    public function adminUser(): static
    {
        return $this->afterCreating(function ($user): void {
            $tenant = Tenant::factory()->create();

            // Create admin profile for the tenant
            $adminProfile = Profile::factory()->create([
                'tenant_id' => $tenant->id,
                'key' => 'ADMIN',
                'name' => 'Admin',
            ]);

            $user->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);
            TenantHelper::setSelectedTenantId($tenant->id);
        });
    }

    public function withSelectedApp(string $app): static
    {
        return $this->afterCreating(function ($user) use ($app): void {
            $appName = mb_strtoupper($app);
            TenantHelper::setSelectedApp($appName);

            // Create or find the TenantApp and assign it to the user's tenant
            $tenant = TenantHelper::getSelectedTenant();
            if ($tenant) {
                $tenantApp = TenantApp::firstOrCreate(
                    ['name' => $appName],
                    [
                        'title' => ucfirst($app),
                        'icon' => mb_strtolower($app) . '::icons.app',
                        'route' => mb_strtolower($app),
                        'is_active' => true,
                    ],
                );
                $tenant->tenantApps()->syncWithoutDetaching([$tenantApp->id]);
            }
        });
    }
}
