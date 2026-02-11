# Modal System

A modal system for Livewire 4 that opens any Livewire component in a modal — no traits, no modifications to your component code.

## Installation
If you're using noerd/noerd, the noerd/modal package is already included as a dependency. To use noerd/modal standalone, install it via Composer:

```bash
composer require noerd/modal
```

The package auto-registers via Laravel's Service Provider system.

## Layout Setup

Add the modal assets to your layout's `<head>`:

```blade
<head>
    ...
    <x-noerd::noerd-modal-assets/>
    ...
</head>
```

Add the modal component at the beginning of `<body>` (before other Livewire components):

```blade
<body x-data>
    <livewire:noerd-modal/> <!-- must be loaded before livewire components -->

    {{ $slot }}
</body>
```

## Opening Modals

Use the `$modal()` Alpine magic function to open any Livewire component in a modal.

### Syntax

```
$modal(componentName, arguments?, source?)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `componentName` | string | Name of the Livewire component |
| `arguments` | object | Optional parameters passed to the component |
| `source` | string | Optional source component for list refresh |

### Basic Usage

```blade
<!-- Open without parameters -->
<button @click="$modal('customer-detail')">
    New Customer
</button>

<!-- Open with parameters -->
<button @click="$modal('customer-detail', { modelId: 123 })">
    Edit Customer
</button>

<!-- Open with source for auto-refresh -->
<button @click="$modal('customer-detail', { modelId: 123 }, 'customers-list')">
    Edit Customer
</button>
```

### Parameters in Components

Parameters are automatically bound to public properties:

```php
<?php

use Livewire\Component;

new class extends Component
{
    public ?int $modelId = null; // Set to 123 when opened with { modelId: 123 }
};
?>

<div class="p-4">
    @if($modelId)
        Editing record: {{ $modelId }}
    @else
        Creating new record
    @endif
</div>
```

## Closing Modals

### Automatic Methods

- **Escape Key**: Pressing Escape closes the topmost modal
- **Close Button**: Built-in X button in the top-right corner

### Programmatic Methods

From within a Livewire component:

```php
// Close the topmost modal
$this->dispatch('closeTopModal');
```

With the `NoerdDetail` trait (automatically refreshes the source list):

```php
// Close modal and refresh the associated list
$this->closeModalProcess('customers-list');

// Close modal and auto-detect list component
$this->closeModalProcess($this->getListComponent());
```

## Event System

| Event | Description |
|-------|-------------|
| `noerdModal` | Opens a modal (dispatched by `$modal()`) |
| `closeTopModal` | Closes the topmost modal |
| `modal-closed-global` | Fired when all modals are closed |
| `refreshList-{component}` | Refreshes a specific list component |

### Dispatching Events

```php
// Open a modal via Livewire
$this->dispatch('noerdModal',
    modalComponent: 'customer-detail',
    arguments: ['modelId' => 123],
    source: 'customers-list'
);

// Close the topmost modal
$this->dispatch('closeTopModal');
```

## Modal Stacking

The modal system supports unlimited nested modals:

- Each modal gets a unique key and iteration number
- Only the topmost modal responds to Escape key
- Z-index is managed automatically
- Closing a modal reveals the one beneath

Example flow:
1. Open `customers-list` → Click row
2. Opens `customer-detail` (modal 1)
3. Click "Add Address" → Opens `address-detail` (modal 2)
4. Press Escape → Closes modal 2, modal 1 remains

## Fullscreen Mode

- Toggle button in the top-right corner (desktop only)
- State persists via session (`modal_fullscreen`)
- Applies to all modals during the session

