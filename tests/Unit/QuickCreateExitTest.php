<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use Noerd\Traits\NoerdDetail;

uses(Tests\TestCase::class);

/**
 * A minimal detail component that uses NoerdDetail but — like the 45 components
 * that hand-roll their store() — never calls storeProcess(). It only assigns
 * $modelId after "saving", mirroring the real hand-rolled pattern. This proves
 * the quick-create exit is now a framework default, not a per-component duty.
 */
class HandRolledQuickCreateStub extends Component
{
    use NoerdDetail;

    public function store(): void
    {
        $this->modelId = 123;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('leaves quick-create mode after a hand-rolled store() that never calls storeProcess()', function (): void {
    Livewire::test(HandRolledQuickCreateStub::class)
        ->set('quickCreate', true)
        ->assertSet('quickCreate', true)
        ->call('store')
        ->assertSet('quickCreate', false)
        ->assertDispatched('resizeTopModal');
});

it('stays in quick-create mode while no record exists yet', function (): void {
    Livewire::test(HandRolledQuickCreateStub::class)
        ->set('quickCreate', true)
        ->assertSet('quickCreate', true)
        ->assertNotDispatched('resizeTopModal');
});
