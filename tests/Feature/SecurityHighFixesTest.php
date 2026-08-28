<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Noerd\Support\LockedPropertiesHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;

uses(TestCase::class);

// ---------------------------------------------------------------------------
// setFieldValue only writes into detailData
// ---------------------------------------------------------------------------

it('setFieldValue writes into detailData but ignores any other property', function (): void {
    $component = new class {
        use NoerdDetail;
    };
    $component->modelId = 5;

    $component->setFieldValue('detailData.name', 'Ok');
    expect($component->detailData['name'])->toBe('Ok');

    // A non-detailData field is a no-op — an authoritative property stays put.
    $component->setFieldValue('modelId', 999);
    expect($component->modelId)->toBe(5);

    // The relationTitle side-channel only fills relationTitles for the field's key.
    $component->setFieldValue('detailData.customer_id', 7, 'Acme');
    expect($component->relationTitles['customer_id'])->toBe('Acme');
});

// ---------------------------------------------------------------------------
// resolvePicklistOptions never invokes a side-effecting method
// ---------------------------------------------------------------------------

it('resolvePicklistOptions invokes array providers only, never a void method', function (): void {
    $component = new class {
        use NoerdDetail;

        public bool $sideEffect = false;

        public function wipeEverything(): void
        {
            $this->sideEffect = true;
        }

        public function statusOptions(): array
        {
            return ['open' => 'Open'];
        }
    };

    expect($component->resolvePicklistOptions('wipeEverything'))->toBe([]);
    expect($component->sideEffect)->toBeFalse();

    expect($component->resolvePicklistOptions('statusOptions'))->toBe(['open' => 'Open']);
});

// ---------------------------------------------------------------------------
// LockedPropertiesHook vetoes client updates to authoritative properties
// ---------------------------------------------------------------------------

it('LockedPropertiesHook rejects client updates to identity/config properties', function (): void {
    $hook = new LockedPropertiesHook();
    $hook->setComponent(new class {
        use NoerdList;
    });

    expect(fn(): mixed => $hook->update('listModel', 'listModel', 'Some\\Model'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn(): mixed => $hook->update('listActionMethod', 'listActionMethod', 'selectAction'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    // A normal client-bound property (search, filters, …) is never vetoed.
    $hook->update('search', 'search', 'abc');
    expect(true)->toBeTrue();
});

it('LockedPropertiesHook leaves non-noerd components untouched', function (): void {
    $hook = new LockedPropertiesHook();
    $hook->setComponent(new class {});

    // No exception even for a protected name — the guard is scoped to noerd traits.
    $hook->update('listModel', 'listModel', 'Some\\Model');
    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// markdown escapes embedded raw HTML
// ---------------------------------------------------------------------------

it('the markdown component escapes embedded HTML while keeping markdown formatting', function (): void {
    $html = Blade::render('<x-noerd::markdown :content="$c" />', [
        'c' => "Hello **bold**\n<script>alert(1)</script>",
    ]);

    expect($html)->toContain('<strong>bold</strong>')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});
