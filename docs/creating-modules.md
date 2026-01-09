# Creating Modules

This guide describes how to develop your own submodules for the Noerd Framework.

## Core Principles

- **Independence**: Modules must be independent from each other
- **No Circular Dependencies**: Modules must not depend on each other
- **Own Resources**: Tests, migrations, seeders belong in the module

## Module Structure

```
app-modules/{module-name}/
├── app-contents/               # YAML configurations (optional)
│   └── {app-key}/
│       ├── lists/
│       ├── details/
│       └── navigation.yml
├── database/
│   ├── migrations/             # Database migrations
│   ├── factories/              # Model factories
│   └── seeders/                # Database seeders
├── resources/
│   ├── views/
│   │   └── livewire/           # Volt components
│   └── lang/
│       ├── de.json             # German translations
│       └── en.json             # English translations
├── routes/
│   └── {module}-routes.php     # Route definitions
├── src/
│   ├── Commands/               # Artisan commands
│   ├── Models/                 # Eloquent models
│   ├── Providers/              # ServiceProvider
│   └── ...
├── tests/
│   ├── Traits/                 # Test helper traits
│   └── Components/             # Component tests
├── composer.json
└── README.md
```

## Step 1: Create Directory Structure

```bash
mkdir -p app-modules/{module-name}/{src/Providers,src/Models,src/Commands}
mkdir -p app-modules/{module-name}/resources/{views/livewire,lang}
mkdir -p app-modules/{module-name}/database/{migrations,factories,seeders}
mkdir -p app-modules/{module-name}/routes
mkdir -p app-modules/{module-name}/tests/{Traits,Components}
```

## Step 2: Create composer.json

```json
{
    "name": "nywerk/{module-name}",
    "description": "Module description",
    "type": "library",
    "version": "v1.0.0",
    "license": "proprietary",
    "require": {
        "laravel/framework": "^11.0|^12.0",
        "livewire/livewire": "^3.4",
        "livewire/volt": "^v1.6.7"
    },
    "require-dev": {
        "pestphp/pest": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "Nywerk\\{ModuleName}\\": "src/",
            "Nywerk\\{ModuleName}\\Tests\\": "tests/",
            "Nywerk\\{ModuleName}\\Database\\Factories\\": "database/factories/",
            "Nywerk\\{ModuleName}\\Database\\Seeders\\": "database/seeders/"
        }
    },
    "minimum-stability": "stable",
    "extra": {
        "laravel": {
            "providers": [
                "Nywerk\\{ModuleName}\\Providers\\{ModuleName}ServiceProvider"
            ]
        }
    }
}
```

### Naming Conventions

| Namespace | Description |
|-----------|-------------|
| `Noerd\*` | Framework modules (core functionality) |
| `Nywerk\*` | Project-specific modules |

## Step 3: Create ServiceProvider

Create `src/Providers/{ModuleName}ServiceProvider.php`:

```php
<?php

namespace Nywerk\{ModuleName}\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class {ModuleName}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load views with namespace
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', '{module-name}');

        // Load JSON translations
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/{module-name}-routes.php');

        // Mount Volt components
        Volt::mount(__DIR__ . '/../../resources/views/livewire');

        // Register commands (optional)
        if ($this->app->runningInConsole()) {
            $this->commands([
                // \Nywerk\{ModuleName}\Commands\InstallCommand::class,
            ]);
        }
    }
}
```

## Step 4: Create Model

Create `src/Models/{Model}.php`:

```php
<?php

namespace Nywerk\{ModuleName}\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nywerk\{ModuleName}\Database\Factories\{Model}Factory;

class {Model} extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): {Model}Factory
    {
        return {Model}Factory::new();
    }
}
```

## Step 5: Create Factory

Create `database/factories/{Model}Factory.php`:

```php
<?php

namespace Nywerk\{ModuleName}\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nywerk\{ModuleName}\Models\{Model};

class {Model}Factory extends Factory
{
    protected $model = {Model}::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
```

## Step 6: Create Migration

Create `database/migrations/{timestamp}_create_{table}_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{table}', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{table}');
    }
};
```

## Step 7: Create Routes

Create `routes/{module-name}-routes.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('{module-name}')
    ->as('{module-name}.')
    ->middleware(['web', 'auth', 'verified'])
    ->group(function (): void {
        Volt::route('/', '{items}-list')->name('index');
        Volt::route('/settings', 'settings-detail')->name('settings');
    });
```

## Step 8: Create Livewire Components

### List Component

Create `resources/views/livewire/{items}-list.blade.php`:

```php
<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Nywerk\{ModuleName}\Models\{Model};

new class extends Component {
    use Noerd;

    public const COMPONENT = '{items}-list';

    public function tableAction(mixed $modelId = null): void
    {
        $this->dispatch('openModal2', componentName: '{item}-detail', id: $modelId);
    }

    public function with(): array
    {
        $query = {Model}::query();

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        $query->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc');

        return [
            'rows' => $query->paginate(self::PAGINATION),
            'tableConfig' => StaticConfigHelper::getTableConfig(self::COMPONENT),
        ];
    }
} ?>

<div>
    <x-noerd::table.list-header :config="$tableConfig" wire:model.live.debounce="search" />
    <x-noerd::table.table-build :rows="$rows" :config="$tableConfig" :component="self::COMPONENT" />
</div>
```

### Detail Component

Create `resources/views/livewire/{item}-detail.blade.php`:

```php
<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
use Nywerk\{ModuleName}\Models\{Model};

new class extends Component {
    use Noerd;

    public const COMPONENT = '{item}-detail';
    public const LIST_COMPONENT = '{items}-list';
    public const ID = '{item}Id';

    public array ${item} = [];
    public ?string ${item}Id = null;

    public function mount({Model} $model): void
    {
        $this->mountModalProcess(self::COMPONENT, $model);
        $this->{item} = $model->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $model = {Model}::updateOrCreate(
            ['id' => $this->{item}Id],
            $this->{item}
        );

        $this->storeProcess($model);
        $this->closeModalProcess(self::LIST_COMPONENT);
    }
} ?>

<div>
    @include('noerd::components.detail.block', $pageLayout)

    <div class="mt-4 flex justify-end gap-2">
        <x-noerd::buttons.button-secondary wire:click="closeModalProcess('{{ self::LIST_COMPONENT }}')">
            {{ __('noerd_label_cancel') }}
        </x-noerd::buttons.button-secondary>
        <x-noerd::buttons.button-primary wire:click="store">
            {{ __('noerd_label_save') }}
        </x-noerd::buttons.button-primary>
    </div>
</div>
```

## Step 9: Create YAML Configurations

### List Configuration

Create `app-contents/{app-key}/lists/{items}-list.yml`:

```yaml
title: {module}_label_{items}
newLabel: {module}_label_new_{item}
component: {item}-detail
disableSearch: false
columns:
  - { field: name, label: {module}_label_name, width: 15 }
  - { field: is_active, label: {module}_label_active, width: 5, type: boolean }
```

### Detail Configuration

Create `app-contents/{app-key}/details/{item}-detail.yml`:

```yaml
title: {module}_label_{item}
description: ''
fields:
  - { name: {item}.name, label: {module}_label_name, type: text, required: true }
  - { name: {item}.is_active, label: {module}_label_active, type: checkbox }
```

### Navigation

Create `app-contents/{app-key}/navigation.yml`:

```yaml
- title: {module}_label_{module}
  name: {module}
  route: {module}.index
  block_menus:
    - title: {module}_nav_overview
      navigations:
        - { title: {module}_nav_{items}, route: {module}.index, heroicon: list-bullet }
```

## Step 10: Add Translations

### resources/lang/de.json

```json
{
    "{module}_label_{items}": "{Items}",
    "{module}_label_{item}": "{Item}",
    "{module}_label_new_{item}": "Neues {Item}",
    "{module}_label_name": "Name",
    "{module}_label_active": "Aktiv",
    "{module}_nav_overview": "Uebersicht",
    "{module}_nav_{items}": "{Items}"
}
```

### resources/lang/en.json

```json
{
    "{module}_label_{items}": "{Items}",
    "{module}_label_{item}": "{Item}",
    "{module}_label_new_{item}": "New {Item}",
    "{module}_label_name": "Name",
    "{module}_label_active": "Active",
    "{module}_nav_overview": "Overview",
    "{module}_nav_{items}": "{Items}"
}
```

## Step 11: Create Tests

### Test Trait

Create `tests/Traits/Creates{ModuleName}User.php`:

```php
<?php

namespace Nywerk\{ModuleName}\Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

trait Creates{ModuleName}User
{
    use RefreshDatabase;

    protected function createUserWith{ModuleName}Access(): array
    {
        $tenant = Tenant::factory()->create();
        $app = TenantApp::firstOrCreate(['name' => '{ModuleName}']);
        $tenant->tenantApps()->attach($app->id);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        return ['user' => $user, 'tenant' => $tenant];
    }
}
```

### Component Test

Create `tests/Components/{Model}ComponentTest.php`:

```php
<?php

declare(strict_types=1);

use Livewire\Volt\Volt;
use Nywerk\{ModuleName}\Models\{Model};
use Nywerk\{ModuleName}\Tests\Traits\Creates{ModuleName}User;

uses(Creates{ModuleName}User::class);

it('can render {items} list', function () {
    ['user' => $user] = $this->createUserWith{ModuleName}Access();

    $this->actingAs($user);

    Volt::test('{items}-list')
        ->assertStatus(200);
});

it('can create {item}', function () {
    ['user' => $user] = $this->createUserWith{ModuleName}Access();

    $this->actingAs($user);

    Volt::test('{item}-detail')
        ->set('{item}.name', 'Test Item')
        ->call('store')
        ->assertHasNoErrors();

    expect({Model}::where('name', 'Test Item')->exists())->toBeTrue();
});
```

## Step 12: Register Module

### Main Project composer.json

Add the module to your main project's `composer.json`:

```json
{
    "require": {
        "nywerk/{module-name}": "^1.0"
    }
}
```

### Update Composer

```bash
composer update nywerk/{module-name}
```

## Step 13: Create TenantApp

```bash
php artisan noerd:create-app
# Name: {ModuleName}
# Route: {module-name}.index
```

## Checklist

- [ ] Directory structure created
- [ ] composer.json configured
- [ ] ServiceProvider implemented
- [ ] Models with factories created
- [ ] Migrations written
- [ ] Routes defined
- [ ] Livewire components created
- [ ] YAML configurations set up
- [ ] Translations added
- [ ] Tests written
- [ ] Module registered in main project
- [ ] TenantApp created

## Next Steps

- [Artisan Commands](artisan-commands.md) - Available CLI commands
- [YAML Configuration](yaml-configuration.md) - Customize configurations
