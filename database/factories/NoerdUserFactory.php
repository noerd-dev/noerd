<?php

declare(strict_types=1);

namespace Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

class NoerdUserFactory extends Factory
{
    protected $model = NoerdUser::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
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
        return $this->state(fn(array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * The installation-level admin flag. `super_admin` is $guarded — only a
     * forced write reaches the column.
     */
    public function superAdmin(): static
    {
        return $this->afterMaking(static function (NoerdUser $user): void {
            $user->forceFill(['super_admin' => true]);
        });
    }

    /**
     * Attaches a fresh tenant AND selects it in the tenant session — the state
     * a screen test needs. Note the session mutation: it changes the request
     * scope for everything that follows in the test.
     */
    public function withExampleTenant(): static
    {
        return $this->afterCreating(function (NoerdUser $user): void {
            $tenant = Tenant::factory()->create();

            $user->tenants()->attach($tenant->id);
            TenantHelper::setSelectedTenantId($tenant->id);
        });
    }

    /**
     * Like withExampleTenant(), but with the ADMIN profile on the pivot — and
     * with the same tenant-session mutation.
     */
    public function adminUser(): static
    {
        return $this->afterCreating(function (NoerdUser $user): void {
            $tenant = Tenant::factory()->create();

            // Create admin profile for the tenant

            $user->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);
            TenantHelper::setSelectedTenantId($tenant->id);
        });
    }

    /**
     * Selects an app in the tenant session and assigns it to the user's tenant.
     * Also a session mutation — call it after withExampleTenant()/adminUser().
     */
    public function withSelectedApp(string $app): static
    {
        return $this->afterCreating(function (NoerdUser $user) use ($app): void {
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
