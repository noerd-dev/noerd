# Header Actions

Header actions let a module contribute small Livewire components to the header of every list and every detail view — for example the layout-editor and object-manager icons the noerd-plus module renders next to the search field. The core knows nothing about the individual actions: it only mounts what modules registered.

## Concept

- **Separate slots for lists and details.** The registry keeps two independent lists: list actions render in the list header (`list-header`), detail actions render in the detail header (`modal-title`). An action that should appear in both contexts must be registered twice — there is no shared slot.
- **One action, one function, one Livewire component.** Every action is its own minimal Livewire component. It renders exactly one button (or nothing) and contains no logic for the other context.
- **Actions own their visibility.** The core always mounts every registered action. The action itself decides in `mount()` whether it has something to show (permissions, current app, available configuration) and renders an empty root when hidden.

## Registering Actions

Register from your module service provider's `boot()` via the `HeaderActionsRegistry` singleton (noerd core):

```php
use Noerd\Services\HeaderActionsRegistry;

public function boot(): void
{
    $registry = app(HeaderActionsRegistry::class);

    // List headers only:
    $registry->registerListAction('my-module::list-header-action-export');

    // Detail headers only:
    $registry->registerDetailAction('my-module::detail-header-action-history');

    // A universal action must be registered for BOTH slots explicitly:
    $registry->registerListAction('my-module::universal-header-action-play-button');
    $registry->registerDetailAction('my-module::universal-header-action-play-button');
}
```

The registered name is a Livewire component name — typically an anonymous view-file component in your module's `resources/views/components/` folder, resolved through your module's Livewire namespace (`Livewire::addNamespace('my-module', viewPath: ...)`).

Registration-based on purpose: when a module is removed, its registration (and its actions) disappear with it — no config cleanup needed.

## The Component Contract

Every action component is mounted with the same two parameters, in both contexts:

| Param | Value |
|-------|-------|
| `model` | The host's declared model class (`$listModel` on lists, `$detailModel` on details) — `null` when the host declares none |
| `component` | The host's Livewire alias, e.g. `customer::customers-list` or `customer::customer-detail` |

Rules for the component itself:

- **Single collapsing root.** Use `<div class="contents">` as the root and render the button inside it only when visible. A hidden action must render an empty root — never `@if` around the root element.
- **Gate in `mount()`.** Compute visibility and any derived state once in `mount()` and store it in `#[Locked]` properties. Authorization is entirely the action's responsibility.
- **Mount-time params only.** The params are passed once at mount. The header re-renders on every Livewire update of the host (e.g. each search keystroke), but nested Livewire components with stable keys are skipped on parent re-renders — your action is mounted once per page lifecycle and never re-runs its gating per keystroke. Do not read live host state.
- Open modals with the noerd modal system (the Alpine `$modal(...)` magic or `Noerd::modal(...)`), never with a hand-rolled overlay.

## Data Conventions: `$listModel` and `$detailModel`

Actions that need to know *what* the header shows read the host's declared Eloquent model class:

```php
// List component
new class extends Component {
    use NoerdList;

    public $listModel = Customer::class;
    public $detailComponent = 'customer::customer-detail';
};

// Detail component
new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = Customer::class;

    public $detailModel = Customer::class;
};
```

- A host without the declaration passes `model: null` — an action that depends on the model must **hide** in that case. There is deliberately no fallback to guessing from the component name.
- From the model class an action can derive everything else:
  - the table: `(new $model())->getTable()`
  - the list component/YAML name: `StaticConfigHelper::modelToListComponent($model)` → `Customer::class` becomes `customers-list`
  - the detail component/YAML name: `StaticConfigHelper::modelToDetailComponent($model)` → `Customer::class` becomes `customer-detail`

## Full Example

A minimal list action that shows an icon for admins and opens a modal:

```blade
{{-- my-module/resources/views/components/list-header-action-export.blade.php --}}
<?php

use Livewire\Attributes\Locked;
use Livewire\Component;

new class () extends Component {
    #[Locked]
    public ?string $table = null;

    public function mount(?string $model, string $component): void
    {
        if ($model === null || ! auth()->user()?->isAdmin()) {
            return;
        }

        $this->table = (new $model())->getTable();
    }
};
?>

<div class="contents">
    @if($table !== null)
        <x-noerd::button variant="icon" icon="arrow-down-tray" type="button"
                         title="{{ __('Export') }}"
                         x-data
                         @click="$modal('my-module::export-modal', { table: '{{ $table }}' })">
            <span class="sr-only">{{ __('Export') }}</span>
        </x-noerd::button>
    @endif
</div>
```

Registered with `$registry->registerListAction('my-module::list-header-action-export')` — that is all; the core renders it in every non-picker, non-compact list header.

## Where Actions Do NOT Render

- Compact/embedded lists (no header at all)
- Picker lists (`returnsSelection`)
- Quick-create detail dialogs

## Built-in Reference Implementations

The noerd-plus module ships four single-purpose actions — one function per component, list and detail deliberately separated:

| Component | Slot | Opens |
|-----------|------|-------|
| `plus::list-header-action-layout-manager` | list | Layout editor for the list YAML derived from `$listModel` |
| `plus::list-header-action-object-manager` | list | Custom-attributes manager for the model's table |
| `plus::detail-header-action-layout-manager` | detail | Layout editor for the detail YAML derived from `$detailModel` |
| `plus::detail-header-action-object-manager` | detail | Custom-attributes manager for the model's table |

Use them as templates for your own actions.
