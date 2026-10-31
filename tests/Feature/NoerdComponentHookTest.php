<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\ComponentHookRegistry;
use Livewire\Livewire;
use Noerd\Livewire\Hooks\ComponentAccessHook;
use Noerd\Livewire\Hooks\LockedPropertiesHook;
use Noerd\Livewire\Hooks\NoerdComponentHook;
use Noerd\Livewire\Hooks\NoerdHooks;
use Noerd\Livewire\Hooks\QuickCreateExitHook;
use Noerd\Livewire\Hooks\ServerOnlyMethodsHook;
use Noerd\Livewire\Hooks\WriteGuardHook;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

class ZzPlainHookComponent extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}

class ZzHookedListComponent extends Component
{
    use NoerdList;

    public $listModel = NoerdUser::class;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-hooked-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return ['title' => 'Users', 'columns' => [['field' => 'name', 'label' => 'Name']]];
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-plain-hook', ZzPlainHookComponent::class);
    Livewire::component('zz-hooked-list', ZzHookedListComponent::class);
});

it('registers every noerd hook through the one list, each a NoerdComponentHook', function (): void {
    foreach (NoerdHooks::ALL as $hook) {
        expect(is_subclass_of($hook, NoerdComponentHook::class))->toBeTrue("{$hook} must extend NoerdComponentHook");
    }

    $registered = (new ReflectionProperty(ComponentHookRegistry::class, 'componentHooks'))->getValue();

    expect(array_values(array_intersect($registered, NoerdHooks::ALL)))->toBe(NoerdHooks::ALL);
});

it('attaches the trait-bound hooks to a noerd list only', function (): void {
    $list = Livewire::test('zz-hooked-list')->instance();
    $plain = Livewire::test('zz-plain-hook')->instance();

    expect(ComponentHookRegistry::getHook($list, LockedPropertiesHook::class))->toBeInstanceOf(LockedPropertiesHook::class)
        ->and(ComponentHookRegistry::getHook($list, ServerOnlyMethodsHook::class))->toBeInstanceOf(ServerOnlyMethodsHook::class)
        // A list is no page: the page-only hooks stay off it.
        ->and(ComponentHookRegistry::getHook($list, QuickCreateExitHook::class))->toBeNull()
        ->and(ComponentHookRegistry::getHook($list, WriteGuardHook::class))->toBeNull()
        // A plain Livewire component carries none of the trait-bound hooks …
        ->and(ComponentHookRegistry::getHook($plain, LockedPropertiesHook::class))->toBeNull()
        ->and(ComponentHookRegistry::getHook($plain, ServerOnlyMethodsHook::class))->toBeNull()
        // … but the admin guard applies to every component.
        ->and(ComponentHookRegistry::getHook($plain, ComponentAccessHook::class))->toBeInstanceOf(ComponentAccessHook::class);
});
