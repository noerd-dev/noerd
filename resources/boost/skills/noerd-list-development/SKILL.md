---
name: noerd-list-development
description: "Use this skill when creating or changing a Noerd list component (*-list.blade.php + lists/*-list.yml): new tables/overviews, list columns, search, sorting, Excel-style column filters, header actions, picklist badges, multi-select/bulk actions, pickers, grid/card mode, compact embedded lists or alternate list views. Triggers on requests like 'add a list for X', 'show Y as a column', 'add a filter/action/bulk delete to the list', 'render the list as cards', 'embed the list in the detail'. Applies to every module built on noerd/noerd."
license: MIT
metadata:
  author: noerd
---

# Noerd List Development

A Noerd list is a Livewire single-file component that uses the `NoerdList` trait and is configured
by a YAML file. The hard rules live in the `noerd/noerd` Boost guideline (`## Noerd Framework`);
this skill is the step-by-step procedure. The package docs are in `vendor/noerd/noerd/docs/`
(`app-modules/noerd/docs/` for a submodule install) — read `list-view.md` first, then
`list-search.md` and `list-filters.md` when search/filters are involved.

## 1. Decide what you are building

| Situation | What to do |
|---|---|
| Rows are one Eloquent model | Slim component with `$listModel` (default) |
| Same, but extra wheres / eager loads | Slim component + `listData()` override using `$this->listQuery()` |
| Rows are not one model (raw/repository query) | Manual query without `$listModel` — accepted, but no column filters |
| List embedded inside a detail/page | Do not hand-wire: use `<x-noerd::detail-lists>` (YAML `lists:`) or `<x-noerd::detail-list>` |
| List used to pick records for an opener | Open it with `Noerd::modal('{module}::{entities}-list', ['multiSelect' => true, 'returnsSelection' => true, 'context' => '…'])` |

## 2. Create the component — `resources/views/components/{entities}-list.blade.php`

Plural name, directly in `components/` (no subfolders). Reference: the `noerd:module` stub
`src/Commands/stubs/module/list.stub`.

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Vendor\Module\Models\Thing;

new class extends Component {
    use NoerdList;

    public $listModel = Thing::class;

    public ?string $detailRoute = 'module.thing.detail';      // route modal, URL rewritten

    public $detailComponent = 'module::thing-detail';        // fallback when the route is missing
}; ?>

<x-noerd::list/>
```

- Nothing else goes into a slim component. `mount()`, `listAction()`, `listData()`, `rendering()`
  come from the trait.
- Default sort: `public function mount(): void { $this->mountList(); $this->setDefaultSort('name'); }`
  — never set `$sortField`/`$sortAsc` directly.
- Custom query: override `listData()` only —
  `$rows = $this->listQuery($this->listModel)->where(...)->with(...)->paginate($this->perPage); return $this->buildList($rows);`
  Keep extra view data (e.g. a summary) in a slim `with()` that shares the query via a private
  helper. Never put the query into `with()` once `$listModel` is declared.

## 3. Create the YAML — `lists/{entities}-list.yml` in BOTH places

`app-configs/{app}/lists/` (installed project copy) AND `app-modules/{module}/app-configs/{app}/lists/`
(module template). Always directly in `lists/`, never in subfolders; block-style YAML only.

```yaml
title: Things
actions:
  - label: New Thing
    route: module.thing.detail        # or `action: listAction` when no route exists
columns:
  - field: name
    label: Name
  - field: status
    label: Status                     # picklist values render as translated badges automatically
  - field: created_at
    label: Created
    type: date
```

Checklist of optional YAML features (all generic, never re-implement in the component):
- `actions:` — several header buttons (`route:` / `action:`, `heroicon`, `style: secondary`)
- `multiSelect: true` + `bulkActions:` (`deleteSelected` is built in; custom bulk methods read
  `$this->selectedRecordIds`)
- `displayMode: grid` + `gridColumns:` — card layout, row click unchanged
- `type: badge` + `options:` — manual badge when no paired detail field exists
- column filters / search: see `list-filters.md` / `list-search.md` (they need `$listModel`)
- alternate views: sibling file `{entities}-list--{key}.yml` (complete standalone config)
- `compact` (embedded) and `minimal` (widget) rendering are chosen by the host, not by YAML

## 4. Wire it up

- Route: `Route::livewire('{app}/{entities}', 'module::{entities}-list')->name('module.{entities}')`
  in the module routes file, plus the detail route the row click opens
  (`Route::livewire('{app}/{entity}/{modelId}', 'module::{entity}-detail')->name('module.{entity}.detail')`).
- Navigation: add the entry to `app-configs/{app}/navigation.yml` (both copies) with a `heroicon`.
- Translations: labels are English texts; add German to `resources/lang/de.json` of the module.

## 5. Test (Pest, inside the module)

- Mount the component (`Livewire::test('module::{entities}-list')`) with factory records and assert
  the rows render; cover a custom `listData()` (filter/eager load) if present.
- Never assert the current YAML content (titles, column lists, route vs. component target) — test
  the mechanics with synthetic data. See the `noerd-testing` skill.

## Done when

- [ ] component is slim (or the `listData()` override ends in `buildList()`)
- [ ] YAML exists in both locations and is block style
- [ ] row click opens the detail (route with component fallback)
- [ ] navigation + routes + `de.json` updated
- [ ] Pest test added and green, `vendor/bin/pint --dirty` run
