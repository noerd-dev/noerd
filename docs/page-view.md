# Page View (NoerdPage)

A `*-page` component hosts everything AROUND a record's form: the page chrome (header, footer,
tabs), the Relation Box, the widget sidebar and — optionally — an embedded slim `*-detail`
component that renders the form itself. The counterpart trait to `NoerdList`/`NoerdDetail` is
`NoerdPage`.

`NoerdDetail` composes `NoerdPage` (`use NoerdPage`), so a detail is "a page plus the model-form
concerns". The split:

| Concern | Trait | Component |
|---------|-------|-----------|
| Chrome, tabs, modal lifecycle, quick-create, list interplay | `NoerdPage` | `{entity}-page.blade.php` |
| Form fields, validation, persistence of ONE model | `NoerdDetail` | `{entity}-detail.blade.php` |

**Details are pure model forms.** Their YAML (`details/{entity}-detail.yml`, mandatory) contains
only `title`, `description`, `theme`, `quickCreate` and `fields` (plus form-level `tabs` when
the fields themselves are tabbed). `widgets:` and `relations:` do NOT belong in a detail YAML —
they are page concerns. A detail opened standalone (e.g. from a relation field) therefore renders
just the form, without widgets or relation box.

## The optional page YAML

A page MAY ship a YAML at `app-configs/{app}/pages/{entity}-page.yml` (module copy at
`app-modules/{module}/app-configs/{app}/pages/`; both copies must be kept in sync). A missing page
YAML is a normal state — hand-built pages define their layout in the component itself
(`StaticConfigHelper::getPageFields()` is silent on miss).

```yaml
title: Account
detail: crm::account-detail
quickCreate: true
relations:
  - label: Contacts
    heroicon: users
    relation: contacts
    route: crm.contacts
    component: contacts-list
    arguments:
      accountId: $modelId
widgets:
  - title: Opportunities
    route: crm.opportunities
    component: crm::opportunities-list
    columns:
      - name
      - amount
    arguments:
      accountId: $modelId
```

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `detail` | The embedded detail Livewire component (full name, e.g. `crm::account-detail`). Drives the generic store roundtrip |
| `quickCreate` | Opt-in for the narrow quick-create modal on new records (also sizes the modal via noerd-modal) |
| `tabs` | Page-level tabs (e.g. Media, Activity Log) — rendered by the page blade via `<x-noerd::tabs>` |
| `relations` | Relation Box tiles (see detail-view.md → Relation Box; the component usage is identical, only the YAML lives on the page now). Each tile may carry `route:` next to `component:` |
| `widgets` | Right-hand widget sidebar rendered by `<x-noerd::detail-grid>` / `<x-noerd::detail-widgets>`. `route:` is the "Show more" target, `component:` the embedded list and the route fallback |

Both `relations` and `widgets` open a list NARROWED by the current record, so their
`route:` resolves the component WITHOUT rewriting the browser URL — see
[Modal System](modal.md#route-modals).

## Component structure

```php
<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Traits\NoerdPage;
use Noerd\Crm\Models\Account;

new class extends Component {
    use NoerdPage;

    #[Url(as: 'accountId', keep: false, except: '')]
    public $modelId = null;

    public $detailModel = Account::class;
    public const LIST_COMPONENT = 'crm::accounts-list';
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Account') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::detail-relations :layout="$pageLayout" :modelId="$modelId"
                               :modelClass="\Noerd\Crm\Models\Account::class"/>

    <x-noerd::detail-grid :layout="$pageLayout" :modelId="$modelId">
        @livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))
    </x-noerd::detail-grid>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
```

- `initPage()` (the trait's `mount()` default) loads the record into `$detailData` when
  `$detailModel` is set, loads the optional page YAML into `$pageLayout` and resolves quick-create.
- The embedded detail is mounted with `embedded: true` — `x-noerd::page` then renders the detail
  chrome-less (no header/footer/scroll wrapper), the page owns all chrome.
- `LIST_COMPONENT` is only needed for namespaced lists; a same-namespace `account-page` derives
  `accounts-list` automatically (`getListComponent()` strips `-page` and `-detail` alike).

## Generic store roundtrip

The save flow between page and embedded detail is fully generic — no per-component events:

1. The page footer's Save calls `NoerdPage::store()`, which dispatches
   **`storeDetail-{detail}`** (suffix = the full component name from the YAML `detail:` key).
2. The detail listens (via `getListeners()`), runs its normal `store()` — identical to a
   standalone save — and ends in `finishStore($model)`.
3. `finishStore()` dispatches **`detailStored-{detail}`** with the model id. The page adopts the
   id, refreshes its `$detailData` snapshot (merge — page-owned keys survive), runs
   `storeProcess()` and finally the protected hook **`afterEmbeddedDetailStored($model)`**.
4. Pages that persist page-owned state (e.g. product groups/variants, uploads) override
   `afterEmbeddedDetailStored()` — see `product-page.blade.php`.

Live form sync: an embedded detail mirrors its form state via **`detailDataUpdated-{detail}`**
(`NoerdDetail::updatedDetailData()` → `syncPayload()`, override the latter to filter the payload —
see `product-detail.blade.php`). The page merges it in `embeddedDetailDataUpdated()` (override to
add side effects, e.g. a change counter for a live preview).

## References

- `app-modules/crm/resources/views/components/account-page.blade.php` + `app-configs/crm/pages/account-page.yml` — the minimal reference pair
- `app-modules/crm/resources/views/components/lead-page.blade.php` — page-level tabs, stage bar, audit tab, two-step qualify flow (`leadQualifyStore` → detail validates/saves → `leadQualifyStored`)
- `app-modules/product/resources/views/components/product-page.blade.php` — heavy `afterEmbeddedDetailStored()` (groups/variants/S3), live preview via `embeddedDetailDataUpdated()`
- Legacy note: settings-style `*-page` components without a page YAML may keep using `NoerdDetail`; the `-page` suffix skips the detail-YAML lookup for them (`NoerdDetail::mountDetailComponent()`)
