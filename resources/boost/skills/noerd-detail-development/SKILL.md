---
name: noerd-detail-development
description: "Use this skill when creating or changing a Noerd detail or page component (*-detail.blade.php, *-page.blade.php + details/*-detail.yml, pages/*-page.yml): record forms, form fields and tabs, relation fields and their *Selected events, relation forms, detail action buttons, embedded lists, the relation box, form themes (default/compact/numbered) or custom store/delete logic. Triggers on 'add a form/detail for X', 'add a field/tab', 'add a button to the detail', 'link the record to Y', 'show related records on the page'. Applies to every module built on noerd/noerd."
license: MIT
metadata:
  author: noerd
---

# Noerd Detail & Page Development

A Noerd detail is a Livewire single-file component using the `NoerdDetail` trait, configured by a
detail YAML that is a *pure model form*. Everything around the form (tabs of a page, relation box,
widgets, embedded detail) is a page (`NoerdPage`). The hard rules live in the `noerd/noerd` Boost
guideline; this skill is the procedure. Docs: `vendor/noerd/noerd/docs/detail-view.md`,
`page-view.md`, `field-types.md`, `relation-field-types.md`, `relation-forms.md`, `themes.md`.

## 1. Decide the shape

| Need | Build |
|---|---|
| Plain record form | `*-detail` (slim, no methods) |
| Form + relation box / widgets / page tabs / quick-create | `*-page` with `detail:` in the page YAML embedding the slim `*-detail` |
| Fields that edit a related model (e.g. default address) | Keep the detail slim; declare `relationForms()` on the MODEL (`DeclaresRelationForms`) |
| Persist through a service | Override `store()`: `validateFromLayout()` → service → `storeProcess($model)` |
| Tenant-singleton settings screen | `*-page` with the `NoerdSettingsPage` trait + `settings/{name}.yml` — stacked full-width fields, may edit several models via `$settingsModels`; see `docs/settings-page.md` |

## 2. Create the detail — `resources/views/components/{entity}-detail.blade.php`

Singular name, directly in `components/`. Reference: `src/Commands/stubs/module/detail.stub`.

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdDetail;
use Vendor\Module\Models\Thing;

new class extends Component {
    use NoerdDetail;

    public $detailModel = Thing::class;          // mandatory

    public ?string $detailPrimary = 'thingId';   // mandatory, literal default, binds $modelId to the URL
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Thing') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
```

- Never redeclare `$modelId`, never add `#[Url]` to it, never store the Eloquent model as a
  property (`$detailData` array is the form state; the model is a local variable only).
- Custom `mount()` starts with `$this->initDetail()`; custom `store()` ends with
  `$this->storeProcess($model)`; custom `delete()` ends with
  `$this->closeModalProcess($this->getListComponent())`.

## 3. Create the YAML — `details/{entity}-detail.yml` in BOTH places

(`app-configs/{app}/details/` and `app-modules/{module}/app-configs/{app}/details/`.)
Allowed keys: `title`, `description`, `theme`, `quickCreate`, `tabs`, `fields`, `actions`, `lists`.
`widgets:` and `relations:` NEVER belong here (page YAML).

```yaml
title: Thing
theme: compact                 # optional: default | compact | numbered
tabs:
  - number: 1
    label: General
  - number: 2
    label: Settings
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
    required: true
  - name: detailData.customer_id
    label: Customer
    type: customerRelation         # a registered relation type (RelationFieldRegistry), never `relation`
    colspan: 6
  - name: detailData.active
    label: Active
    type: checkbox
    colspan: 6
    tab: 2
```

- Field names always carry the `detailData.` prefix; `required: true` is the only validation source.
- Relation field → the field component syncs `detailData.x_id` and `relationTitles.x_id` itself; a
  `{entity}Selected($id)` handler on the component is only needed to REACT to the selection (derive
  further fields) and then writes `$this->detailData['x_id']` / `$this->relationTitles['x_id']`;
  never add a display property.
- Use `type: spacer` to keep an empty grid cell; use `type: block` for nested groups.
- Tabs: `<x-noerd::tab-content>` renders them for you; hand-rolled panels must use
  `<x-noerd::tab-panels>` / `<x-noerd::tab-panel>`.
- `actions:` renders a button row automatically (`action:` method, `route:`/`modalComponent:` modal,
  `url:` link, `confirm`, `requiresId`, `showIf`, `viewExists`).
- `lists:` embeds compact lists below the form via `<x-noerd::detail-lists :layout="$pageLayout" :modelId="$modelId" />`.

## 4. Page (only when needed) — `{entity}-page.blade.php` + `pages/{entity}-page.yml`

```yaml
title: Thing
detail: module::thing-detail
relations:
  - label: Contacts
    heroicon: users
    relation: contacts
    route: module.contacts            # list narrowed by the record, no URL rewrite
    component: contacts-list          # fallback
    arguments:
      thingId: $modelId
```

Blade: header → `<x-noerd::detail-relations :layout="$pageLayout" :modelId="$modelId" :modelClass="Thing::class" />`
→ `@livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))`.
Save roundtrip page↔detail is generic (`storeDetail-*` / `detailStored-*` events); override
`afterEmbeddedDetailStored($model)` for page-owned persistence.

## 5. Routes, navigation, translations

- `Route::livewire('{app}/{entity}/{modelId}', 'module::{entity}-detail')->name('module.{entity}.detail')`
  — this named route is what lists (`$detailRoute`), actions (`route:`) and `Noerd::modalRoute()` use.
- English labels in YAML, German in the module's `resources/lang/de.json`.

## 6. Test (Pest, inside the module)

- Store success: `->set('detailData', validDetailPayload(Thing::class, [...]))` + override only the
  asserted fields, or mount an existing record via `modelId`.
- Validation: `->assertHasErrors(requiredLayoutFields($component))` — never hard-code which field
  is required. Never assert the current theme/tabs/fields of a shipped YAML.

## Done when

- [ ] `$detailModel` + `$detailPrimary` declared, no model property, no `#[Url]` on `$modelId`
- [ ] detail YAML is a pure form (no widgets/relations), exists in both locations, block style
- [ ] relation fields use `relationTitles.*` + `{entity}Selected`
- [ ] named detail route registered; list row click / actions reference it with component fallback
- [ ] Pest tests (store + validation) green, `vendor/bin/pint --dirty` run
