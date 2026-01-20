# Detail Views

Detail views display forms for creating and editing records.

## File Structure

```
app-modules/{module}/resources/views/livewire/{resource}-detail.blade.php
app-configs/{app}/details/{resource}-detail.yml
```

**Naming Convention:** Always use singular form for details (e.g., `customer-detail`, `user-detail`).

## Example Detail Model

```php
<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Models\Customer;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    // The Noerd trait provides modal handling, validation, and YAML loading
    use Noerd;

    // Unique identifier - must match the YAML filename
    public const COMPONENT = 'customer-detail';
    // The list component to reload after save/delete
    public const LIST_COMPONENT = 'customers-list';
    // Property name for the model ID (used for URL binding)
    public const ID = 'customerId';

    // URL parameter binding - allows direct linking to a record
    #[Url(keep: false, except: '')]
    public $customerId = null;

    // The model data as array - field names in YAML reference this (e.g., customer.name)
    public array $customer;

    // Called when component is initialized
    public function mount(Customer $model): void
    {
        // If opened via modal, customerId is passed from the list
        if ($this->customerId) {
            $model = Customer::find($this->customerId);
        }

        // Loads YAML config into $pageLayout and sets up modal state
        $this->mountModalProcess(self::COMPONENT, $model);
        // Convert model to array for form binding
        $this->customer = $model->toArray();
    }

    // Called when save button is clicked
    public function store(): void
    {
        // Validates fields marked as required: true in YAML
        $this->validateFromLayout();

        // Add tenant_id before saving
        $this->customer['tenant_id'] = auth()->user()->selected_tenant_id;
        // Create or update the record
        $customer = Customer::updateOrCreate(['id' => $this->customerId], $this->customer);

        // Shows green checkmark feedback
        $this->showSuccessIndicator = true;

        // If new record, update the ID for subsequent saves
        if ($customer->wasRecentlyCreated) {
            $this->customerId = $customer['id'];
        }
    }

    // Called when delete button is clicked
    public function delete(): void
    {
        $customer = Customer::find($this->customerId);
        $customer->delete();
        // Closes modal and dispatches reload event to the list
        $this->closeModalProcess(self::LIST_COMPONENT);
    }
}; ?>

{{-- Page wrapper with modal support --}}
<x-noerd::page :disableModal="$disableModal">
    {{-- Modal/page header --}}
    <x-slot:header>
        <x-noerd::modal-title>Customer</x-noerd::modal-title>
    </x-slot:header>

    {{-- Renders all fields from YAML config with tab support --}}
    <x-noerd::tab-content :layout="$pageLayout" :modelId="$customerId" />

    {{-- Footer with delete and save buttons --}}
    <x-slot:footer>
        {{-- showDelete controls whether delete button is visible (only for existing records) --}}
        <x-noerd::delete-save-bar :showDelete="isset($customerId)"/>
    </x-slot:footer>
</x-noerd::page>
```

## Required Constants

| Constant | Type | Description |
|----------|------|-------------|
| `COMPONENT` | string | Unique identifier (e.g., `customer-detail`) |
| `LIST_COMPONENT` | string | Associated list component (e.g., `customers-list`) |
| `ID` | string | Property name for the model ID (e.g., `customerId`) |

## Key Methods

### mount()

Initializes the component with model data:

```php
public function mount(Customer $model): void
{
    if ($this->customerId) {
        $model = Customer::find($this->customerId);
    }

    $this->mountModalProcess(self::COMPONENT, $model);
    $this->customer = $model->toArray();
}
```

`mountModalProcess()` loads the YAML configuration into `$pageLayout`.

### store()

Saves the model data:

```php
public function store(): void
{
    $this->validateFromLayout();

    $this->customer['tenant_id'] = auth()->user()->selected_tenant_id;
    $customer = Customer::updateOrCreate(['id' => $this->customerId], $this->customer);

    $this->showSuccessIndicator = true;

    if ($customer->wasRecentlyCreated) {
        $this->customerId = $customer['id'];
    }
}
```

`validateFromLayout()` validates fields marked as `required: true` in the YAML.

### delete()

Deletes the record and closes the modal:

```php
public function delete(): void
{
    $customer = Customer::find($this->customerId);
    $customer->delete();
    $this->closeModalProcess(self::LIST_COMPONENT);
}
```

`closeModalProcess()` dispatches events to reload the list and close the modal.

## YAML Configuration

Create a YAML file at `app-configs/{app}/details/{resource}-detail.yml`:

```yaml
title: customer_label_customer
fields:
  - { name: customer.name, label: customer_label_name, type: text, required: true, colspan: 6 }
  - { name: customer.email, label: customer_label_email, type: text, colspan: 6 }
  - { name: customer.phone, label: customer_label_phone, type: text, colspan: 6 }
  - { name: customer.company_name, label: customer_label_company, type: text, colspan: 6 }
```

### Options

| Option | Type | Description |
|--------|------|-------------|
| `title` | string | Form title (translation key) |
| `description` | string | Description text |
| `tabs` | array | Tab definitions |
| `fields` | array | Field definitions |

### Field Options

| Option | Type | Description |
|--------|------|-------------|
| `name` | string | Model attribute path (e.g., `customer.name`) |
| `label` | string | Field label (translation key) |
| `type` | string | Field type |
| `required` | bool | Required field |
| `colspan` | int | Width (1-12, grid system) |
| `tab` | int | Tab number (default: 1) |

### Field Types

| Type | Description |
|------|-------------|
| `text` | Single-line text |
| `textarea` | Multi-line text |
| `richText` | TipTap rich text editor |
| `checkbox` | Checkbox |
| `select` | Dropdown |
| `picklist` | Dynamic dropdown |
| `relation` | Relation select with modal |
| `image` | Image upload |

## Blade Template

```blade
<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Customer</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$customerId">
        <x-slot:tab1>
            {{-- Additional content for tab 1 --}}
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($customerId)"/>
    </x-slot:footer>
</x-noerd::page>
```

### Components

| Component | Description |
|-----------|-------------|
| `x-noerd::page` | Page wrapper with modal support |
| `x-noerd::modal-title` | Modal/page header |
| `x-noerd::tab-content` | Renders fields from YAML with tab support |
| `x-noerd::delete-save-bar` | Footer with delete and save buttons |

## Tabs

For forms with multiple tabs:

```yaml
title: customer_label_customer
tabs:
  - { number: 1, label: customer_tab_general }
  - { number: 2, label: customer_tab_settings }
fields:
  - { name: customer.name, label: customer_label_name, type: text, colspan: 6 }
  - { name: customer.notes, label: customer_label_notes, type: textarea, colspan: 12, tab: 2 }
```

Fields without a `tab` property default to tab 1.

## Relation Fields

For selecting related models:

```yaml
- name: customer.category_id
  label: customer_label_category
  type: relation
  relationField: relationTitles.category_id
  modalComponent: categories-list
```

Handle the selection event:

```php
#[On('categorySelected')]
public function categorySelected($categoryId): void
{
    $category = Category::find($categoryId);
    $this->customer['category_id'] = $category->id;
    $this->relationTitles['category_id'] = $category->name;
}
```

## Noerd Trait Properties

| Property | Type | Description |
|----------|------|-------------|
| `$pageLayout` | array | YAML configuration |
| `$showSuccessIndicator` | bool | Show success feedback |
| `$relationTitles` | array | Display values for relations |

**Note:** The model ID property (e.g., `$customerId`) is defined per component via `const ID` and is not part of the Noerd trait.

## Next Steps

- [Lists](03-lists.md) - Create list views
- [YAML Configuration](yaml-configuration.md) - Full YAML reference
