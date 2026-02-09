## Noerd Framework

Noerd is a YAML-driven modular framework for Laravel applications with list and detail components.

### File Naming Conventions
- Lists: `{entities}-list.blade.php` (plural, e.g., `users-list.blade.php`)
- Details: `{entity}-detail.blade.php` (singular, e.g., `user-detail.blade.php`)
- Components: Place directly in `resources/views/components/`

### YAML Configuration
- Lists: `app-configs/{app}/lists/{entities}-list.yml`
- Details: `app-configs/{app}/details/{entity}-detail.yml`
- Navigation: `app-configs/{app}/navigation.yml`
- Always use block style formatting, never flow/inline style like `@verbatim{{ key: value }}@endverbatim`
- When modifying YAML files, sync both `app-configs/` and `app-modules/{module}/app-configs/`

### Module Independence
- Tests, migrations, seeders belong in the module (`app-modules/{module}/`)
- Modules must be independent from each other
- Translations: `app-modules/{module}/resources/lang/{de,en}.json`
- Use `loadJsonTranslationsFrom()` in ServiceProvider

### Eloquent Models
- Use `@verbatim$guarded = []@endverbatim` instead of `@verbatim$fillable@endverbatim`
- Never store Eloquent models as Livewire component properties

### Detail Components
- Property: `@verbatim$modelNameData@endverbatim` (array) for `wire:model` binding
- Model only as local variable in methods, never as property
- Relation events: `{entity}Selected` pattern (e.g., `customerSelected`)
- Display values: `@verbatim$this->relationTitles['field_id']@endverbatim`
- YAML fields: `name: modelNameData.fieldname`

@verbatim
<code-snippet name="Detail Component Example" lang="php">
public array $bankAccountData = [];  // Only the array is a property

public function mount(BankAccount $bankAccount): void
{
    if ($this->modelId) {
        $bankAccount = BankAccount::find($this->modelId);
    }
    $this->bankAccountData = $bankAccount->toArray();
}

public function store(): void
{
    $bankAccount = BankAccount::updateOrCreate(
        ['id' => $this->bankAccountId],
        $this->bankAccountData
    );
}
</code-snippet>

<code-snippet name="Relation Selection Example" lang="php">
#[On('customerSelected')]
public function customerSelected($customerId): void
{
    $customer = Customer::find($customerId);
    $this->model['customer_id'] = $customer->id;
    $this->relationTitles['customer_id'] = $customer->name;
}
</code-snippet>
@endverbatim

### Tabs in Detail Components
- Define tabs in YAML with `number` and `label`
- Fields without `tab` property default to Tab 1
- Filter fields in Blade with `array_filter()`

@verbatim
<code-snippet name="YAML Tab Definition" lang="yaml">
title: Example
tabs:
  - number: 1
    label: module_tab_general
  - number: 2
    label: module_tab_settings
fields:
  - name: model.name
    label: Name
    type: text
    colspan: 6
  - name: model.setting_a
    label: Setting A
    type: checkbox
    colspan: 6
    tab: 2
</code-snippet>

<code-snippet name="Blade Tab Filtering" lang="blade">
<x-noerd::tabs :layout="$pageLayout" />

@foreach($pageLayout['tabs'] ?? [['number' => 1]] as $tab)
    <div x-show="currentTab === {{ $tab['number'] }}">
        @php
            $tabFields = array_filter($pageLayout['fields'] ?? [], fn($field) => ($field['tab'] ?? 1) === $tab['number']);
            $tabLayout = array_merge($pageLayout, ['fields' => array_values($tabFields)]);
        @endphp
        @include('noerd::components.detail.block', $tabLayout)
    </div>
@endforeach
</code-snippet>
@endverbatim

### Icons
- Navigation: Use heroicons (`heroicon: cog-6-tooth`)
- Apps: Use module icons (`noerd::icons.app`)

### Translations
- Format: `{module}_{key}` (e.g., `accounting_dashboard`)
- Labels: `{module}_label_{key}`
- Navigation: `{module}_nav_{key}`
- Tabs: `{module}_tab_{key}`
- Place in module's lang directory based on key prefix
- Root `lang/` directory only for generic translations without module prefix
