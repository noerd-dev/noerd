# Header Actions

Header actions let a module contribute small Livewire components to the header of every list and every detail view — for example an icon rendered next to the search field on every list. The core knows nothing about the individual actions: it only mounts what modules registered.

## Concept

- **Separate slots for lists and details.** The registry keeps two independent lists: list actions render among the list header controls (`table/list-controls-registry`: in the right group of the filter row in the standard list header; injected next to the buttons by `modal-title` for a `NoerdList` host with a custom header slot), detail actions render top-right in the head row of the FIRST form block a `*-detail` renders (`detail/block-head`, decided by `Noerd\Support\DetailHeaderActions`) — never in the modal header. An embedded detail renders chrome-less but its form block is still its own, so every detail carries its own actions and a page embedding two details shows two sets. A `*-page` hosts them only on a field grid of its own (`fields:` in the page YAML). An action that should appear in both contexts must be registered twice — there is no shared slot.
- **Once per host.** The actions are mounted in the first block only — a second tab, a nested `type: block` or a further direct `@include('noerd::components.detail.block', …)` of a hand-built detail never mounts them again (Livewire keys children per parent). Nothing to wire: `x-noerd::tab-content` and direct block includes both go through the same claim.
- **One action, one function, one Livewire component.** Every action is its own minimal Livewire component. It renders exactly one button (or nothing) and contains no logic for the other context.
- **Actions own their visibility.** The core always mounts every registered action. The action itself decides in `mount()` whether it has something to show (permissions, current app, available configuration) and renders an empty root when hidden.

## A Component's Own Buttons: the `blockActions` Slot

The registry is for buttons EVERY detail gets. A single detail (or a page with its own field
grid) puts its own buttons into the same head row through the `blockActions` slot of
`x-noerd::tab-content` — no registry, no Livewire child, rendered once on the first form block:

```blade
<x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
    <x-slot:blockActions>
        <x-noerd::button variant="control" icon="arrow-down-tray" type="button"
                         wire:click="export" title="{{ __('Export') }}">
            <span class="sr-only">{{ __('Export') }}</span>
        </x-noerd::button>
    </x-slot:blockActions>
</x-noerd::tab-content>
```

A hand-built detail that includes `noerd::components.detail.block` directly passes the markup
on its FIRST include instead: `'blockActions' => view('inventory::components.item-block-actions')`
(any `Htmlable`, e.g. `new HtmlString(...)`). The slot content renders before the registry
actions; the row shows as soon as either exists.

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
| `component` | The host's Livewire alias, e.g. `inventory::items-list` or `inventory::item-detail` (an embedded detail passes its OWN alias, not the hosting page's) |

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

    public $listModel = Item::class;
    public ?string $detailRoute = 'inventory.item.detail';
    public $detailComponent = 'inventory::item-detail';
};

// Detail component
new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'itemId';
    public $detailModel = Item::class;
};
```

- A host without the declaration passes `model: null` — an action that depends on the model must **hide** in that case. There is deliberately no fallback to guessing from the component name.
- From the model class an action can derive everything else:
  - the table: `(new $model())->getTable()`
  - the list component/YAML name: `StaticConfigHelper::modelToListComponent($model)` → `Item::class` becomes `items-list`
  - the detail component/YAML name: `StaticConfigHelper::modelToDetailComponent($model)` → `Item::class` becomes `item-detail`

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

- Compact/embedded and minimal lists (no header at all)
- Picker lists (`returnsSelection`)
- Quick-create detail dialogs
- Modal headers of details and pages — the detail slot is the form block, not the header
- `*-page` components whose page YAML declares no `fields:` (their embedded detail carries the actions)
- Details and pages that render no `noerd::components.detail.block` at all (a hand-built body without a YAML form)

## Design Guidance

Keep each action a SINGLE-purpose component — one function per component, and
register list and detail variants separately: the two slots share no markup,
and a combined component ends up branching on its context everywhere.
