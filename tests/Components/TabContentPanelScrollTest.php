<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('renders tab panels as individual scroll containers inside an equal-height grid', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-noerd::tab-content
            :layout="['tabs' => [['number' => 1, 'label' => 'One'], ['number' => 2, 'label' => 'Two']], 'fields' => []]"
            :modelId="1"
            :showBlock="false" />
    BLADE);

    expect($html)
        ->toContain('grid min-h-0 grid-rows-1')
        ->toContain('shrink-0 pb-6')
        ->toContain('min-h-0 overflow-y-auto');
});

it('renders tab-panels and tab-panel as generic stacking components', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-noerd::tab-panels class="pt-4">
            <x-noerd::tab-panel :number="1" class="flex">One</x-noerd::tab-panel>
            <x-noerd::tab-panel :number="2" :show="'$wire.someFlag'">Two</x-noerd::tab-panel>
        </x-noerd::tab-panels>
    BLADE);

    expect($html)
        ->toContain('grid min-h-0 grid-rows-1')
        ->toContain('pt-4')
        ->toContain('min-h-0 overflow-y-auto -mx-6 px-6 flex')
        ->toContain('x-show="$wire.someFlag"')
        ->toContain("currentTab === 2 ? 'visible' : 'invisible pointer-events-none'");
});

it('renders the page body as a single consolidated scroll container', function (): void {
    $html = Blade::render('<x-noerd::page>Body Content</x-noerd::page>');

    assertElementHasClasses($html, ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto']);

    expect($html)->not->toMatch('/class="[^"]*"\s+class="/');
});

it('stretches the body into a flex column only for components with tabs', function (): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    $bodyClasses = ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto', 'flex', 'flex-col'];

    assertElementHasClasses(Livewire::test('noerd::noerd-user-detail')->html(), $bodyClasses);

    assertNoElementHasClasses(Livewire::test('noerd::noerd-users-list')->html(), $bodyClasses);
});
