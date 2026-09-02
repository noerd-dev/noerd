<?php

declare(strict_types=1);

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Noerd\Support\LockedPropertiesHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class);

/*
 | The client-callable surface of the noerd traits: authoritative properties
 | ($listModel, $listActionMethod, …) may never be updated from the browser,
 | the store tail is not callable at all, and a picklist provider is only ever
 | invoked when it actually returns options.
 */

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
    expect(fn(): mixed => $hook->update('search', 'search', 'abc'))->not->toThrow(CannotUpdateLockedPropertyException::class);
});

it('LockedPropertiesHook leaves non-noerd components untouched', function (): void {
    $hook = new LockedPropertiesHook();
    $hook->setComponent(new class {});

    // No exception even for a protected name — the guard is scoped to noerd traits.
    expect(fn(): mixed => $hook->update('listModel', 'listModel', 'Some\\Model'))
        ->not->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps finishStore and storeProcess out of the client-callable surface', function (): void {
    expect((new ReflectionMethod(NoerdDetail::class, 'finishStore'))->isPublic())->toBeFalse()
        ->and((new ReflectionMethod(NoerdPage::class, 'storeProcess'))->isPublic())->toBeFalse();
});

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
