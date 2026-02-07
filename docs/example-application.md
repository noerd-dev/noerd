# Example Application

This guide walks through building a complete Noerd module using the **Study** app as a real example. The Study app manages study materials, summaries, and flashcards.

## Module Structure

```bash
app-modules/study/
├── composer.json
├── app-configs/
│   ├── stubs/
│   │   └── add_study_tenant_app.php.stub
│   └── study/
│       ├── navigation.yml
│       ├── lists/
│       │   ├── study-materials-list.yml
│       │   ├── summaries-list.yml
│       │   └── flashcards-list.yml
│       └── details/
│           ├── study-material-detail.yml
│           ├── summary-detail.yml
│           └── flashcard-detail.yml
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── lang/
│   │   ├── de.json
│   │   └── en.json
│   └── views/
│       ├── components/icons/
│       │   └── app.blade.php
│       └── livewire/
│           ├── study-materials-list.blade.php
│           ├── study-material-detail.blade.php
│           ├── summaries-list.blade.php
│           ├── summary-detail.blade.php
│           ├── flashcards-list.blade.php
│           └── flashcard-detail.blade.php
├── routes/
│   └── study-routes.php
├── src/
│   ├── Commands/
│   │   └── StudyInstallCommand.php
│   ├── Models/
│   │   ├── StudyMaterial.php
│   │   ├── Summary.php
│   │   └── Flashcard.php
│   └── Providers/
│       └── StudyServiceProvider.php
└── tests/
    ├── Components/
    ├── Feature/
    ├── Models/
    └── Traits/
        └── CreatesStudyUser.php
```

## composer.json

```json
{
    "name": "noerd/study",
    "description": "Study module for Noerd Framework",
    "type": "library",
    "version": "0.1.0",
    "license": "proprietary",
    "require": {
        "noerd/noerd": "^0.2"
    },
    "autoload": {
        "psr-4": {
            "Nywerk\\Study\\": "src/",
            "Nywerk\\Study\\Tests\\": "tests/",
            "Nywerk\\Study\\Database\\Factories\\": "database/factories/",
            "Nywerk\\Study\\Database\\Seeders\\": "database/seeders/"
        }
    },
    "minimum-stability": "stable",
    "extra": {
        "laravel": {
            "providers": [
                "Nywerk\\Study\\Providers\\StudyServiceProvider"
            ]
        }
    }
}
```

The `extra.laravel.providers` block enables auto-discovery. Autoload maps cover `src/`, `tests/`, `database/factories/`, and `database/seeders/`.

The module is registered in the main project's `composer.json` as a path repository and required via `"noerd/study": "^0.1"`.

## Service Provider

```php
<?php

namespace Nywerk\Study\Providers;

use Illuminate\Support\ServiceProvider;

use Nywerk\Study\Commands\StudyInstallCommand;

class StudyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'study');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/study-routes.php');


        if ($this->app->runningInConsole()) {
            $this->commands([
                StudyInstallCommand::class,
            ]);
        }
    }
}
```

- `loadMigrationsFrom()` — Registers module migrations.
- `loadViewsFrom()` — Registers views with the `study` namespace.
- `loadJsonTranslationsFrom()` — Loads JSON translations from the module.
- `loadRoutesFrom()` — Loads the module's route file.
- `commands()` — Registers Artisan commands (only in console).

## Migration

Migrations live in `app-modules/study/database/migrations/`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('study_materials', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('author')->nullable();
            $table->integer('page_count')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->integer('publication_year')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_materials');
    }
};
```

Key patterns:
- **Tenant isolation**: Every table has a `tenant_id` foreign key referencing `tenants`.
- **Index on tenant_id**: Always add for query performance.
- **Cascade delete**: Use `cascadeOnDelete()` for child tables (e.g., summaries, flashcards).
- **Nullable foreign keys**: Optional relations use `nullOnDelete()`.

## Model

```php
<?php

namespace Nywerk\Study\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Media\Models\Media;
use Noerd\Models\Tenant;
use Noerd\Traits\BelongsToTenant;
use Noerd\Traits\HasListScopes;
use Nywerk\Study\Database\Factories\StudyMaterialFactory;

class StudyMaterial extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasListScopes;

    protected $table = 'study_materials';

    protected $guarded = [];

    protected array $searchable = [
        'title',
        'author',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    protected static function newFactory(): Factory
    {
        return StudyMaterialFactory::new();
    }

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'publication_year' => 'integer',
        ];
    }
}
```

**Traits:**

| Trait | Purpose |
|-------|---------|
| `BelongsToTenant` | Automatically scopes queries to the current tenant |
| `HasFactory` | Enables factory usage for testing |
| `HasListScopes` | Provides scopes used by list components (search, sort) |

**Properties:**
- `$table` — Explicit table name (required when it does not follow Laravel's convention).
- `$guarded` — Mass-assignment protection. Use `[]` for all fields assignable or protect sensitive fields only.
- `$searchable` — Fields searched by `HasListScopes` when the user types in the search box.

**`casts()` method:** Define attribute casting as a method (Laravel 12 convention).

**`newFactory()` method:** Required for module factories since they live outside the default directory.

## Factory

```php
<?php

namespace Nywerk\Study\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nywerk\Study\Models\StudyMaterial;

class StudyMaterialFactory extends Factory
{
    protected $model = StudyMaterial::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'page_count' => $this->faker->numberBetween(100, 800),
            'media_id' => null,
            'publication_year' => $this->faker->numberBetween(1990, 2025),
        ];
    }
}
```

Conventions:
- Set `$model` explicitly (required for module factories).
- Use `$this->faker` for generating test data.
- Set `tenant_id` to a default value; tests override it.
- Set nullable foreign keys to `null` by default.

## List YAML

Each list needs a YAML file at `app-configs/{app}/lists/{resources}-list.yml`.

**study-materials-list.yml:**

```yaml
title: study_label_study_materials
newLabel: study_label_new_study_material
component: study-material-detail
disableSearch: false
redirectAction: ''
columns:
  - field: title
    label: study_label_title
    width: 25
  - field: author
    label: study_label_author
    width: 20
  - field: page_count
    label: study_label_page_count
    width: 15
  - field: publication_year
    label: study_label_publication_year
    width: 15
  - field: created_at
    label: study_label_created_at
    width: 15
    type: date
```

**summaries-list.yml** (with relation column):

```yaml
title: study_label_summaries
newLabel: study_label_new_summary
component: summary-detail
disableSearch: false
redirectAction: ''
columns:
  - field: title
    label: study_label_title
    width: 30
  - field: studyMaterial
    label: study_label_book
    width: 30
    type: relation_link
    modalComponent: study-material-detail
    idField: study_material_id
    idParam: modelId
  - field: created_at
    label: study_label_created_at
    width: 20
    type: date
```

The `relation_link` column type creates a clickable link that opens the related record's detail modal. It requires `modalComponent`, `idField`, and `idParam`.

## List Component

```php
<?php

use Livewire\Component;
use Noerd\Scopes\SortScope;
use Noerd\Traits\NoerdList;
use Nywerk\Study\Models\StudyMaterial;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'study-material-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with()
    {
        $rows = StudyMaterial::withoutGlobalScope(SortScope::class)
            ->orderBy($this->sortField ?: 'title', $this->sortAsc ? 'asc' : 'desc')
            ->paginate(self::PAGINATION);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        if ((int) request()->id) {
            $this->listAction(request()->id);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
```

| Part | Purpose |
|------|---------|
| `NoerdList` trait | Provides all list functionality |
| `listAction()` | Opens the detail modal via `noerdModal` event with `['modelId' => $modelId]` |
| `$this->getComponentName()` | Returns the current component name for the `source` parameter |
| `with()` | Returns `listConfig` built from query results via `$this->buildList($rows)` |
| `rendering()` | Handles direct URL access with query parameters (e.g., `?id=5` or `?create=1`) |

**Filtering by parent record** (e.g., `summaries-list`): Add a `$studyMaterialId` property and use `->when($this->studyMaterialId, ...)` to conditionally filter. Replace relation objects with their title string for display using a `foreach` loop.

## Detail YAML

**study-material-detail.yml** (with tabs and embedded lists):

```yaml
title: study_label_study_material
description: ''
tabs:
  - number: 1
    label: study_tab_general
  - label: study_tab_summaries
    component: summaries-list
    arguments:
      studyMaterialId: $studyMaterialId
    requiresId: true
  - label: study_tab_flashcards
    component: flashcards-list
    arguments:
      studyMaterialId: $studyMaterialId
    requiresId: true
fields:
  - name: detailData.title
    label: study_label_title
    type: text
    colspan: 6
    required: true
  - name: detailData.author
    label: study_label_author
    type: text
    colspan: 6
  - name: detailData.page_count
    label: study_label_page_count
    type: number
    colspan: 6
  - name: detailData.publication_year
    label: study_label_publication_year
    type: number
    colspan: 6
  - name: detailData.media_id
    label: study_label_cover_image
    type: relation
    colspan: 12
    relationField: media
    modalComponent: media-list
```

- Tab 1 uses `number: 1` and displays form fields.
- Tabs 2+ embed list components with `component` and `arguments`.
- `requiresId: true` — these tabs only appear after the record is saved.
- `$studyMaterialId` passes the current model's ID to the embedded list.
- Field names use `detailData.` prefix: `detailData.title`.

**summary-detail.yml** (with relation field):

```yaml
title: study_label_summary
description: ''
fields:
  - name: detailData.title
    label: study_label_title
    type: text
    colspan: 12
    required: true
  - name: detailData.study_material_id
    label: study_label_study_material
    type: relation
    colspan: 12
    relationField: relationTitles.study_material_id
    modalComponent: study-materials-list
  - name: detailData.content
    label: study_label_content
    type: richText
    colspan: 12
    rows: 15
```

The `relation` type opens a list modal for selection. The `relationField` points to the display value in `relationTitles`.

## Detail Component

```php
<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;
use Nywerk\Study\Models\StudyMaterial;

new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = StudyMaterial::class;

    public function store(): void
    {
        $this->validateFromLayout();

        $this->detailData['tenant_id'] = auth()->user()->selected_tenant_id;
        $studyMaterial = StudyMaterial::updateOrCreate(
            ['id' => $this->modelId],
            $this->detailData
        );

        $this->showSuccessIndicator = true;

        if ($studyMaterial->wasRecentlyCreated) {
            $this->modelId = $studyMaterial->id;
        }
    }

    public function delete(): void
    {
        $studyMaterial = StudyMaterial::find($this->modelId);
        $studyMaterial->delete();
        $this->closeModalProcess($this->getListComponent());
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('study_study_material') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
```

**Constant:**

| Constant | Purpose |
|----------|---------|
| `DETAIL_CLASS` | The Eloquent model class for this detail component |

**Properties (provided by NoerdDetail trait):**
- `$modelId` — Model ID, automatically bound to URL
- `$detailData` — Model data as an array. The Eloquent model is **never** stored as a property
- `$pageLayout` — YAML configuration loaded automatically

**Methods:**
- `mount()` — Handled by the trait automatically
- `store()` — Validates via `validateFromLayout()`, saves with `updateOrCreate()`
- `delete()` — Deletes the record and calls `closeModalProcess()`
- `$this->getListComponent()` — Automatically determines the associated list component

## Relation Selection

When a detail has a `relation` field, handle the selection event:

```php
#[On('studyMaterialSelected')]
public function studyMaterialSelected($studyMaterialId): void
{
    $studyMaterial = StudyMaterial::find($studyMaterialId);
    $this->detailData['study_material_id'] = $studyMaterial->id;
    $this->relationTitles['study_material_id'] = $studyMaterial->title;
}
```

- Event name follows `{entity}Selected` pattern.
- Always use `$this->relationTitles['field_id']` for the display value.
- Pre-populate `relationTitles` in `mount()` from existing data and `$relations`.

## Routes

```php
<?php

use Illuminate\Support\Facades\Route;

use Nywerk\Study\Http\Controllers\FlashcardPrintController;

Route::group(['middleware' => ['web', 'auth', 'verified']], function (): void {
    Route::livewire('study/study-materials', 'study-materials-list')->name('study.study-materials');
    Route::livewire('study/study-material/{model}', 'study-material-detail')->name('study.study-material.detail');
    Route::livewire('study/summaries', 'summaries-list')->name('study.summaries');
    Route::livewire('study/summary/{model}', 'summary-detail')->name('study.summary.detail');
    Route::livewire('study/flashcards', 'flashcards-list')->name('study.flashcards');
    Route::livewire('study/flashcard/{model}', 'flashcard-detail')->name('study.flashcard.detail');
    Route::livewire('study/flashcards-print', 'flashcard-print-detail')->name('study.flashcards-print');
    Route::get('study/flashcards-print/pdf', [FlashcardPrintController::class, 'print'])
        ->name('study.flashcards-print.pdf');
});
```

- Use `Route::livewire(')` for Livewire Volt components.
- List routes use the plural form: `study/study-materials`.
- Detail routes include `{model}`: `study/study-material/{model}`.
- Route names: `study.{resource}` for lists, `study.{resource}.detail` for details.
- All routes use `web`, `auth`, and `verified` middleware.

## Navigation YAML

```yaml
-
  title: Study
  name: study
  hidden: true
  route: study.study-materials
  block_menus:
    -
      title: study_nav_learning
      navigations:
        -
          title: study_nav_study_materials
          route: study.study-materials
          heroicon: book-open
          newComponent: study-material-detail
        -
          title: study_nav_summaries
          route: study.summaries
          heroicon: document-text
          newComponent: summary-detail
        -
          title: study_nav_flashcards
          route: study.flashcards
          heroicon: rectangle-stack
          newComponent: flashcard-detail
        -
          title: study_nav_print_flashcards
          route: study.flashcards-print
          heroicon: printer
```

- `hidden: true` — App is accessed via the app switcher, not top-level menu.
- `newComponent` — Shows a "+" button that opens the detail modal for creating a new record.
- `heroicon` — Icon name from the Heroicons library.

## Translations

All keys use the module prefix `study_` followed by a category:

| Prefix | Purpose | Example |
|--------|---------|---------|
| `study_nav_` | Navigation items | `study_nav_study_materials` |
| `study_label_` | Field labels, buttons, headings | `study_label_title` |
| `study_tab_` | Tab labels | `study_tab_general` |
| `study_` (entity) | Entity names (modal titles) | `study_study_material` |

Example entries:

```json
{
    "study_nav_study_materials": "Lernmaterialien",
    "study_label_title": "Titel",
    "study_tab_general": "Allgemein",
    "study_study_material": "Lernmaterial"
}
```

- Translations with a module prefix **must** be placed in the module's `resources/lang/` directory.
- YAML files reference translation keys directly without namespace prefix.
- In Blade: `{{ __('study_label_title') }}`.

## Install Command

The install command registers the module as a tenant app via the `HasModuleInstallation` trait:

```php
protected $signature = 'noerd:install-study {--force : Overwrite existing files without asking}';
```

| Method | Purpose |
|--------|---------|
| `getModuleName()` | Display name (e.g., `'Study'`) |
| `getModuleKey()` | Lowercase key for config paths (e.g., `'study'`) |
| `getDefaultAppTitle()` | Title shown in the app switcher |
| `getAppIcon()` | View path for the app icon (e.g., `'study::icons.app'`) |
| `getAppRoute()` | Default route when opening the app |
| `getSourceDir()` | Path to the module's YAML config directory |
| `getSnippetTitle()` | Title used in the installation stub |
| `getAdditionalSubdirectories()` | Extra directories to copy during install |

The `RequiresNoerdInstallation` trait ensures the base framework is installed first.

## Tests

Tests live in `app-modules/study/tests/` and use the PEST format.

### Test Trait

```php
<?php

namespace Nywerk\Study\Tests\Traits;

use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Models\User;

trait CreatesStudyUser
{
    protected function withStudyModule(): User
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id);

        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('STUDY');

        return $user;
    }
}
```

### Model Test

```php
it('can create a study material', function (): void {
    $tenant = Tenant::factory()->create();

    $studyMaterial = StudyMaterial::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Clean Code',
        'author' => 'Robert C. Martin',
    ]);

    $this->assertDatabaseHas('study_materials', [
        'title' => 'Clean Code',
        'tenant_id' => $tenant->id,
    ]);
});

it('has summaries relationship', function (): void {
    $tenant = Tenant::factory()->create();
    $studyMaterial = StudyMaterial::factory()->create(['tenant_id' => $tenant->id]);
    Summary::factory()->create([
        'tenant_id' => $tenant->id,
        'study_material_id' => $studyMaterial->id,
    ]);

    $this->assertCount(1, $studyMaterial->summaries);
});
```

### Component Test

```php
it('validates the data', function (): void {
    $user = $this->withStudyModule();
    $this->actingAs($user);

    Livewire::test('summary-detail')
        ->call('store')
        ->assertHasErrors(['detailData.title'])
        ->assertHasErrors(['detailData.study_material_id']);
});

it('handles study material selection correctly', function (): void {
    $user = $this->withStudyModule();
    $studyMaterial = StudyMaterial::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
    ]);
    $this->actingAs($user);

    Livewire::test('summary-detail')
        ->call('studyMaterialSelected', $studyMaterial->id)
        ->assertSet('detailData.study_material_id', $studyMaterial->id)
        ->assertSet('relationTitles.study_material_id', $studyMaterial->title);
});
```

### Running Tests

```bash
# Run all study module tests
php artisan test --compact app-modules/study/tests

# Run a specific test file
php artisan test --compact app-modules/study/tests/Models/StudyMaterialTest.php
```
