<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

/**
 * The gap between the page header and the first body element must be the same
 * everywhere, no matter which children render. A page/detail body gets it from
 * the x-noerd::page chrome; a list host's body has no chrome padding (the list
 * brings its own spacing), so a tab bar sitting above the list adds it itself.
 */
it('gives the tab bar of a list host the same top gap the page chrome provides', function (): void {
    $html = Livewire::test(TabbedListSpacingComponent::class)->assertOk()->html();

    expect($html)->toContain('class="w-full shrink-0 pb-6 pt-6"');
});

it('does not double the gap when the page chrome already pads the body', function (): void {
    $html = Livewire::test(TabbedPageSpacingComponent::class)->assertOk()->html();

    expect($html)->toContain('class="w-full shrink-0 pb-6"')
        ->not->toContain('class="w-full shrink-0 pb-6 pt-6"');
});

it('pads the body of a page host but not the body of a list host', function (): void {
    expect(Livewire::test(TabbedPageSpacingComponent::class)->html())
        ->toContain('flex-1 min-h-0 px-6 overflow-y-auto pt-6 pb-8');

    expect(Livewire::test(TabbedListSpacingComponent::class)->html())
        ->not->toContain('flex-1 min-h-0 px-6 overflow-y-auto pt-6 pb-8');
});

/** A list host whose body starts with a tab bar (the object-manager pattern). */
class TabbedListSpacingComponent extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'listConfig' => $this->buildList([['id' => 1, 'name' => 'Alice']], [
                'title' => 'Things',
                'columns' => [
                    ['field' => 'name', 'label' => 'Name'],
                ],
            ]),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <x-noerd::page>
                <x-slot:header>
                    <x-noerd::modal-title :listControls="false">Things</x-noerd::modal-title>
                </x-slot:header>
                <x-noerd::tabs>
                    <x-noerd::tab :tabNumber="1">First</x-noerd::tab>
                </x-noerd::tabs>
                <x-noerd::list hideHead />
            </x-noerd::page>
            BLADE;
    }
}

/** The same tab bar on a page host, whose body is padded by the chrome. */
class TabbedPageSpacingComponent extends Component
{
    use NoerdPage;

    public function render(): string
    {
        return <<<'BLADE'
            <x-noerd::page>
                <x-slot:header>
                    <x-noerd::modal-title>Things</x-noerd::modal-title>
                </x-slot:header>
                <x-noerd::tabs>
                    <x-noerd::tab :tabNumber="1">First</x-noerd::tab>
                </x-noerd::tabs>
            </x-noerd::page>
            BLADE;
    }
}
