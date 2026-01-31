# Create a Detail View

Detail pages display and edit individual records with forms.

![Noerd Example App](/assets/detail.png "Detail View")

## File Locations

**YAML Configuration:**
```
app-configs/{app}/details/{name}-detail.yml
```

**Livewire Component:**
```
app-modules/{module}/resources/views/livewire/{name}-detail.blade.php
```

## YAML Configuration

Example: `app-configs/accounting/details/customer-detail.yml`

```yaml
title: accounting_label_customer_details
description: ''
tabs:
  - number: 1
    label: customer_master_data
  - label: customer_invoices
    component: invoices-list
    arguments:
      customerId: '$customerId'
    requiresId: true
fields:
  - name: customerData.name
    label: accounting_label_name
    type: text
    required: true
  - name: customerData.company_name
    label: accounting_label_company_name
    type: text
  - name: customerData.email
    label: accounting_label_email
    type: text
  - name: customerData.phone
    label: accounting_label_phone
    type: text
  - name: customerData.address
    label: accounting_label_address
    type: text
  - name: customerData.zipcode
    label: accounting_label_zip_code
    type: text
  - name: customerData.city
    label: accounting_label_city
    type: text
```

## Detail Properties

| Property | Description |
|----------|-------------|
| `title` | Page title (translation key) |
| `description` | Optional description text |
| `tabs` | Array of tab definitions |
| `fields` | Array of form field definitions |

## Tab Properties

| Property | Description |
|----------|-------------|
| `number` | Tab index (1-based) |
| `label` | Tab label (translation key) |
| `component` | Embedded Livewire component |
| `arguments` | Arguments passed to embedded component |
| `requiresId` | Only show tab when editing existing record |

## Field Properties

| Property | Description |
|----------|-------------|
| `name` | Property path (e.g., `customerData.name`) |
| `label` | Field label (translation key) |
| `type` | Field type (text, textarea, checkbox, relation, etc.) |
| `required` | Mark field as required |
| `colspan` | Grid column span (1-12) |
| `tab` | Tab number (defaults to 1) |

## Livewire Component

Example: `customer-detail.blade.php`

```php
<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Nywerk\Customer\Models\Customer;
use Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'customer-detail';
    public const LIST_COMPONENT = 'customers-list';
    public const ID = 'customerId';

    #[Url(keep: false, except: '')]
    public $customerId = null;

    public array $customerData = [];

    public function mount(Customer $customer): void
    {
        if ($this->customerId) {
            $customer = Customer::find($this->customerId);
        }

        $this->mountModalProcess(self::COMPONENT, $customer);
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $this->customerData['tenant_id'] = auth()->user()->selected_tenant_id;
        $customer = Customer::updateOrCreate(['id' => $this->customerId], $this->customerData);

        $this->showSuccessIndicator = true;

        if ($customer->wasRecentlyCreated) {
            $this->customerId = $customer->id;
        }
    }

    public function delete(): void
    {
        $customer = Customer::find($this->customerId);
        $customer->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Kunde</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$customerId">
        <x-slot:tab1>
            {{-- Custom content for tab 1 --}}
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($customerId)"/>
    </x-slot:footer>
</x-noerd::page>
```

## Key Concepts

- Property naming: `$customerData` (array) for form binding, never store the Eloquent model as property
- `COMPONENT`, `LIST_COMPONENT`, `ID` constants identify the component
- `mountModalProcess()` initializes the form with model data
- `validateFromLayout()` validates against YAML-defined rules
- `<x-noerd::tab-content>` renders tabs and fields from YAML
- `<x-noerd::delete-save-bar>` provides save/delete buttons

## Naming Conventions

- Lists: `{plural}-list.blade.php` (e.g., `customers-list.blade.php`)
- Details: `{singular}-detail.blade.php` (e.g., `customer-detail.blade.php`)
- Components must be placed directly in the `livewire/` folder, not in subfolders

## Next Steps

- [Components](components.md) - Learn about available UI components
- [YAML Configuration](yaml-configuration.md) - Deep dive into YAML options
- [Creating Modules](creating-modules.md) - Build independent modules
