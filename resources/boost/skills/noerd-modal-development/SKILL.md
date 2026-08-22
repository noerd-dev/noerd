---
name: noerd-modal-development
description: "Use this skill whenever a Noerd backend screen needs a dialog, popup, picker, confirmation, import/review/editor overlay or a record opened on top of the current page. Covers the decision between Noerd::modalRoute()/$modalRoute (addressable record, URL rewritten) and Noerd::modal()/$modal (action dialogs, pickers, narrowed lists), the mandatory modal chrome (x-noerd::page + modal-title + tab-content + footer), closing and returning results to the opener. Triggers on 'open X in a modal', 'add a confirmation/dialog', 'let the user pick a Y', 'show the record as a popup', and on any attempt to hand-roll an overlay in a Livewire component."
license: MIT
metadata:
  author: noerd
---

# Noerd Modal Development

Every backend modal uses the noerd modal system — never a hand-rolled `fixed inset-0` overlay or an
Alpine `x-show` dialog inside a component. Hard rules: `noerd/noerd` Boost guideline (Core Rules,
"Route modal vs. component modal"). Full reference: `vendor/noerd/noerd/docs/modal.md`.

## 1. Route or component? (decide first)

Ask: *paste the resulting URL into a fresh tab — does it show the same thing?*

| Yes → **route modal** | No → **component modal** |
|---|---|
| ONE addressable record (`*-detail`, `*-page`, or a list that IS a record, e.g. an object manager) | action dialogs (`*-modal`, `*-confirmation`, `*-review`, `*-import`, `*-editor`) |
| `Noerd::modalRoute('{app}.{entity}.detail', ['modelId' => $id])` / `$modalRoute(...)` | `Noerd::modal('{module}::{component}', ['key' => $value])` / `$modal(...)` |
| URL rewritten to the record `+ ?modal=true`, restored on close, reload reopens the modal | pickers (`multiSelect`, `returnsSelection`, `context`, `selectMode`, …) and lists narrowed by a parent record (route allowed with `rewriteUrl: false`) |
| Requirements: target uses `NoerdDetail`/`NoerdPage` (or `NoerdList` for record-lists), a named `Route::livewire('{app}/{entity}/{modelId}', …)` exists, every identity argument is a route parameter | |

**Always keep the component as fallback** — `Noerd::modalFor($route, $component, $args)` in PHP;
in YAML/properties pair `route:` + `modalComponent:`, `$detailRoute` + `$detailComponent`,
`newRoute:` + `newComponent:`. The route wins when registered; the component opens when the owning
module is not installed.

Where `route:` is available: list `$detailRoute`, list header action `route:`, detail action
`route:`, relation-box tile / widget `route:`, list column `type: relation_link`, relation field
`detailRoute:`, navigation `modalRoute:` / `newRoute:`, page/detail tab `modalRoute:`, dashboard
card `route=`.

## 2. Build the modal as its OWN Livewire component

`resources/views/components/{name}-modal.blade.php` (action dialogs) — for records the modal IS the
existing `*-detail`/`*-page`, nothing extra to build.

```blade
<?php

use Livewire\Component;

new class extends Component {
    public ?int $modelId = null;          // arguments passed by the opener become public props

    public function save(): void
    {
        // ... domain logic ...
        $this->dispatch('thingMarked', id: $this->modelId);   // result event for the opener
        $this->dispatch('closeTopModal');
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Mark as Contacted') }}</x-noerd::modal-title>
    </x-slot:header>

    {{-- YAML-driven body --}}
    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />
    {{-- …or a custom body without YAML --}}
    <x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">
        <x-slot:tab1>
            {{-- fields --}}
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto flex items-center gap-2">
            <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">{{ __('Cancel') }}</x-noerd::button>
            <x-noerd::button variant="primary" wire:click="save">{{ __('Save') }}</x-noerd::button>
        </div>
    </x-slot:footer>
</x-noerd::page>
```

- Title only via `<x-noerd::modal-title>` in the header slot — never a floating `<div class="text-xl">`.
- Body always inside `<x-noerd::tab-content>`; the page chrome guarantees the vertical spacing, do
  not add `pb-*` padding. Full-bleed: `<x-noerd::page :bodyPadding="false">`.
- `mount()` must be side-effect free: modal children are re-mounted on every stack update.

## 3. Open, close, return

- Open from PHP: `Noerd::modal(...)` / `Noerd::modalRoute(...)`; from Blade/Alpine: `$modal(...)` /
  `$modalRoute(...)`. From YAML: `route:` / `modalComponent:` (+ `arguments:` with the `$modelId` token).
- Close: `wire:click="$dispatch('closeTopModal')"` or `$this->dispatch('closeTopModal')`.
- Result: dispatch a custom event; the opener listens with `#[On('thingMarked')]`. Pickers dispatch
  `recordsSelected` (`ids`, `context`) — filter by `context` in the opener.
- Detail components finish with `storeProcess()` / `closeModalProcess()` which close the modal and
  refresh the opening list automatically.

## 4. Test

- Component modals: `Livewire::test('module::x-modal', ['modelId' => $id])->call('save')->assertDispatched('thingMarked')`.
- Route vs. component is configuration — prove the mechanics with `registerTestLivewireRoute()`
  from `tests/helpers.php` (route wins when registered, component otherwise); never assert which
  one a shipped YAML currently uses.

## Done when

- [ ] no hand-rolled overlay; the modal is its own component (or the record's detail/page)
- [ ] route for addressable records, component for dialogs/pickers — with component fallback
- [ ] standard chrome (`x-noerd::page` + `modal-title` + `tab-content` + footer buttons)
- [ ] closes via `closeTopModal`, returns results via a custom event
