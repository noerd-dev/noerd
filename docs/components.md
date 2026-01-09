# Components

The Noerd Framework provides an extensive library of Blade and Livewire components.

## Blade Components

Blade components are located in `resources/views/components/`.

### Component Categories

| Category | Path | Description |
|----------|------|-------------|
| Buttons | `components/buttons/` | Buttons and actions |
| Detail | `components/detail/` | Form components |
| Elements | `components/elements/` | UI elements (badges, avatars) |
| Forms | `components/forms/` | Form fields |
| Icons | `components/icons/` | Icon components |
| Layouts | `components/layouts/` | Page layouts |
| Modal | `components/modal/` | Modal dialogs |
| Table | `components/table/` | Table components |

### Button Components

#### Primary Button

```blade
<x-noerd::buttons.button-primary wire:click="save">
    Save
</x-noerd::buttons.button-primary>
```

#### Secondary Button

```blade
<x-noerd::buttons.button-secondary wire:click="cancel">
    Cancel
</x-noerd::buttons.button-secondary>
```

#### Delete Button

```blade
<x-noerd::buttons.button-delete wire:click="delete">
    Delete
</x-noerd::buttons.button-delete>
```

### Form Components

#### Text Input

```blade
<x-noerd::forms.input
    wire:model="model.name"
    label="Name"
    required
/>
```

#### Textarea

```blade
<x-noerd::forms.textarea
    wire:model="model.description"
    label="Description"
    rows="5"
/>
```

#### Select

```blade
<x-noerd::forms.select
    wire:model="model.category_id"
    label="Category"
    :options="$categories"
/>
```

#### Checkbox

```blade
<x-noerd::forms.checkbox
    wire:model="model.active"
    label="Active"
/>
```

### Table Components

#### Table Build

The central component for rendering YAML-configured tables:

```blade
<x-noerd::table.table-build
    :rows="$rows"
    :config="$tableConfig"
    :component="self::COMPONENT"
/>
```

#### List Header

```blade
<x-noerd::table.list-header
    :config="$tableConfig"
    wire:model.live.debounce="search"
/>
```

### Detail Components

#### Block

Renders form fields based on YAML configuration:

```blade
<x-noerd::detail.block
    :layout="$pageLayout"
    :model="$model"
/>
```

### Layout Components

#### App Layout

The main layout for authenticated users:

```blade
<x-app-layout>
    {{-- Content --}}
</x-app-layout>
```

#### Guest Layout

Layout for unauthenticated pages (login, etc.):

```blade
<x-noerd::layouts.guest>
    {{-- Login Form --}}
</x-noerd::layouts.guest>
```

### Modal Components

```blade
<x-noerd::modal.modal
    :show="$showModal"
    title="Edit"
>
    {{-- Modal Content --}}
</x-noerd::modal.modal>
```

### Tabs Component

For tab-based layouts:

```blade
<x-noerd::tabs :layout="$pageLayout" />
```

## Livewire Volt Components

### List Component

Standard structure for lists:

```php
<?php
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use App\Models\Item;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'items-list';

    public function tableAction(mixed $modelId = null): void
    {
        $this->dispatch('openModal2', componentName: 'item-detail', id: $modelId);
    }

    public function with(): array
    {
        $query = Item::query();

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

Standard structure for details/forms:

```php
<?php
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
use App\Models\Item;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'item-detail';
    public const LIST_COMPONENT = 'items-list';
    public const ID = 'itemId';

    public array $item = [];
    public ?string $itemId = null;

    public function mount(Item $model): void
    {
        $this->mountModalProcess(self::COMPONENT, $model);
        $this->item = $model->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $model = Item::updateOrCreate(
            ['id' => $this->itemId],
            $this->item
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

## The Noerd Trait

The `Noerd` trait (`src/Traits/Noerd.php`) provides central functionality.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$modelId` | `?string` | ID of current model |
| `$search` | `string` | Search term |
| `$sortField` | `string` | Sort field |
| `$sortAsc` | `bool` | Sort ascending |
| `$currentTab` | `int` | Current tab (URL parameter) |
| `$pageLayout` | `array` | YAML configuration |
| `$showSuccessIndicator` | `bool` | Show success indicator |
| `$activeTableFilters` | `array` | Active table filters |

### Methods

#### sortBy

Changes the sorting:

```php
public function sortBy(string $field): void
```

#### mountModalProcess

Loads configuration and model data:

```php
public function mountModalProcess(string $component, $model): void
{
    $this->pageLayout = StaticConfigHelper::getComponentFields($component);
    $this->model = $model->toArray();
    $this->modelId = $model->id;
}
```

#### closeModalProcess

Closes modal and updates list:

```php
public function closeModalProcess(?string $source = null): void
```

#### storeProcess

Called after saving:

```php
public function storeProcess($model): void
{
    $this->showSuccessIndicator = true;
    if ($model->wasRecentlyCreated) {
        $this->modelId = $model['id'];
    }
}
```

#### validateFromLayout

Validates based on YAML configuration:

```php
public function validateFromLayout(): void
```

Fields with `required: true` in YAML are validated as `required`.

### Events

| Event | Description |
|-------|-------------|
| `reloadTable-{COMPONENT}` | Reload table |
| `close-modal-{COMPONENT}` | Close modal |

## Best Practices

### Component Naming

- Lists: `{resource}-list` (plural) - e.g., `users-list`
- Details: `{resource}-detail` (singular) - e.g., `user-detail`

### File Naming

- Lists: `{resources}-list.blade.php`
- Details: `{resource}-detail.blade.php`

### Trait Constants

Always define these constants:

```php
public const COMPONENT = 'items-list';        // Component name
public const LIST_COMPONENT = 'items-list';   // Only in details
public const ID = 'itemId';                   // ID property name
```

## Next Steps

- [YAML Configuration](yaml-configuration.md) - Create configurations
- [Creating Modules](creating-modules.md) - Develop your own modules
