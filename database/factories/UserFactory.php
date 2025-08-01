<?php

namespace Noerd\Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Models\UserRole;
use Nywerk\Liefertool\Models\Gastrofix;
use Nywerk\Liefertool\Models\Setting;
use Nywerk\Liefertool\Models\Text;
use Nywerk\Product\Models\Menu;

class UserFactory extends Factory
{
    protected $model = User::class;


    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
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

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withCanteenAndMenu(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->canteenModule()->create();
            Menu::factory()->withCategories()->create([
                'tenant_id' => $restaurant->id,
            ]);

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();

            Setting::factory()->create([
                'tenant_id' => $restaurant->id,
            ]);
        });
    }

    public function withDeliveryAndMenu(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->deliveryModule()->create();

            Menu::factory()->withCategories()->create([
                'tenant_id' => $restaurant->id,
            ]);

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();

            Setting::factory()->create([
                'tenant_id' => $restaurant->id,
            ]);

            Text::factory()->create([
                'tenant_id' => $restaurant->id,
            ]);
        });
    }

    public function withStoreAndMenu(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->storeModule()->create();
            Menu::factory()->withCategories()->create([
                'tenant_id' => $restaurant->id,
            ]);

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();
        });
    }

    public function withRestaurantAndMenu(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->restaurantModule()->create();
            Menu::factory()->withCategories()->create([
                'tenant_id' => $restaurant->id,
            ]);

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();

            Setting::factory()->create([
                'tenant_id' => $restaurant->id,
            ]);
        });
    }

    public function withRestaurantAndGastrofix(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->restaurantModule()->create();
            $restaurant->module_gastrofix = true;
            $restaurant->save();

            Gastrofix::factory()->create([
                'tenant_id' => $restaurant->id,
                'default_waiter_product' => 100,
                'default_invoice_product' => 200,
            ]);

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();

            Setting::factory()->create([
                'tenant_id' => $restaurant->id,
            ]);
        });
    }

    public function withRestaurantAndSettings()
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->restaurantModule()->create();
            $restaurant->save();

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();

            Setting::factory()->create([
                'tenant_id' => $restaurant->id,
            ]);
        });
    }

    public function withContentModule(): static
    {
        return $this->afterCreating(function ($user): void {
            $tenant = Tenant::factory()->contentModule()->create();

            Menu::factory()->withCategories()->create([
                'tenant_id' => $tenant->id,
            ]);

            $user->tenants()->attach($tenant->id);
            $user->selected_tenant_id = $tenant->id;
            $user->save();
        });
    }

    public function withPdmModule(): static
    {
        return $this->afterCreating(function ($user): void {
            $tenant = Tenant::factory()->pdmModule()->create();

            Menu::factory()->withCategories()->create([
                'tenant_id' => $tenant->id,
            ]);

            $user->tenants()->attach($tenant->id);
            $user->selected_tenant_id = $tenant->id;
            $user->save();
        });
    }

    public function smsClient(): static
    {
        return $this->afterCreating(function ($user): void {
            $restaurant = Tenant::factory()->create();

            $user->tenants()->attach($restaurant->id);
            $user->selected_tenant_id = $restaurant->id;
            $user->save();
        });
    }

    public function withLegalRegisterModule(): static
    {
        return $this->afterCreating(function ($user): void {
            $tenant = Tenant::factory()->create();

            // Attach the Legal Register module
            $tenant->tenantApps()->attach(TenantApp::where('name', 'LEGAL-REGISTER')->first()->id);

            // Create ADMIN profile for this tenant
            $adminProfile = new Profile();
            $adminProfile->key = 'ADMIN';
            $adminProfile->name = 'Administrator';
            $adminProfile->tenant_id = $tenant->id;
            $adminProfile->save();

            // Create ADMIN user role for this tenant
            $adminRole = new UserRole();
            $adminRole->key = 'ADMIN';
            $adminRole->name = 'Administrator';
            $adminRole->description = 'Full administrative access';
            $adminRole->tenant_id = $tenant->id;
            $adminRole->save();

            // Attach user to tenant with admin profile
            $user->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);

            // Attach admin role to user
            $user->roles()->attach($adminRole->id);

            $user->selected_tenant_id = $tenant->id;
            $user->save();
        });
    }

    public function adminUser(): static
    {
        return $this->afterCreating(function ($user): void {
            $tenant = Tenant::factory()->canteenModule()->create();

            // Create admin profile for the tenant
            $adminProfile = Profile::factory()->create([
                'tenant_id' => $tenant->id,
                'key' => 'ADMIN',
                'name' => 'Administrator',
            ]);

            Menu::factory()->withCategories()->create([
                'tenant_id' => $tenant->id,
            ]);

            $user->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);
            $user->selected_tenant_id = $tenant->id;
            $user->save();

            Setting::factory()->create([
                'tenant_id' => $tenant->id,
            ]);
        });
    }
}
