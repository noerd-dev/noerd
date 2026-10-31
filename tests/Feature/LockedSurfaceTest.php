<?php

declare(strict_types=1);

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Noerd\Livewire\Hooks\LockedPropertiesHook;
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

it('LockedPropertiesHook never lets the client switch deletion back on', function (): void {
    $hook = new LockedPropertiesHook();
    $hook->setComponent(new class {
        use NoerdDetail;
    });

    expect(fn(): mixed => $hook->update('deletable', 'deletable', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn(): mixed => $hook->mount(['deletable' => true]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('LockedPropertiesHook is skipped for non-noerd components', function (): void {
    // The guard is scoped to the noerd traits: Livewire asks skip() right after
    // setComponent() and never attaches the hook to a plain component.
    $plain = new LockedPropertiesHook();
    $plain->setComponent(new class {});

    $list = new LockedPropertiesHook();
    $list->setComponent(new class {
        use NoerdList;
    });

    expect($plain->skip())->toBeTrue()
        ->and($list->skip())->toBeFalse();
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

    expect(\Livewire\invade($component)->resolvePicklistOptions('wipeEverything'))->toBe([]);
    expect($component->sideEffect)->toBeFalse();

    expect(\Livewire\invade($component)->resolvePicklistOptions('statusOptions'))->toBe(['open' => 'Open']);
});
