<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

describe('body padding', function (): void {

    it('pads the page body vertically for detail components regardless of rendered children', function (): void {
        assertElementHasClasses(
            Livewire::test('noerd-test::page-chrome-detail')->html(),
            ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto', 'pt-6', 'pb-8'],
        );
    });

    it('keeps list bodies unpadded — lists own their internal spacing', function (): void {
        assertNoElementHasClasses(
            Livewire::test('noerd-test::page-chrome-list')->html(),
            ['overflow-y-auto', 'pt-6', 'pb-8'],
        );
    });

    it('pads a plain page body and supports the bodyPadding opt-out', function (): void {
        assertElementHasClasses(
            Blade::render('<x-noerd::page>Body</x-noerd::page>'),
            ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto', 'pt-6', 'pb-8'],
        );

        assertNoElementHasClasses(
            Blade::render('<x-noerd::page :bodyPadding="false">Body</x-noerd::page>'),
            ['overflow-y-auto', 'pt-6'],
        );
    });
});

/*
 | The gap between the page header and the first body element must be the same
 | everywhere, no matter which children render. A page/detail body gets it from
 | the x-noerd::page chrome; a list host's body has no chrome padding (the list
 | brings its own spacing), so a tab bar sitting above the list adds it itself.
 */
describe('tab bar spacing', function (): void {

    it('gives the tab bar of a list host the same top gap the page chrome provides', function (): void {
        $html = Livewire::test('noerd-test::page-chrome-list', ['withTabs' => true])->assertOk()->html();

        assertElementHasClasses($html, ['w-full', 'shrink-0', 'pb-6', 'pt-6']);
    });

    it('does not double the gap when the page chrome already pads the body', function (): void {
        $html = Livewire::test('noerd-test::page-chrome-detail')->assertOk()->html();

        assertElementHasClasses($html, ['w-full', 'shrink-0', 'pb-6']);
        assertNoElementHasClasses($html, ['w-full', 'shrink-0', 'pt-6']);
    });
});

describe('tab panel scrolling', function (): void {

    it('renders tab panels as individual scroll containers inside an equal-height grid', function (): void {
        $html = Blade::render(<<<'BLADE'
            <x-noerd::tab-content
                :layout="['tabs' => [['number' => 1, 'label' => 'One'], ['number' => 2, 'label' => 'Two']], 'fields' => []]"
                :modelId="1"
                :showBlock="false" />
        BLADE);

        assertElementHasClasses($html, ['grid', 'min-h-0', 'grid-rows-1']);
        assertElementHasClasses($html, ['shrink-0', 'pb-6']);
        assertElementHasClasses($html, ['min-h-0', 'overflow-y-auto']);
    });

    it('renders tab-panels and tab-panel as generic stacking components', function (): void {
        $html = Blade::render(<<<'BLADE'
            <x-noerd::tab-panels class="pt-4">
                <x-noerd::tab-panel :number="1" class="flex">One</x-noerd::tab-panel>
                <x-noerd::tab-panel :number="2" :show="'$wire.someFlag'">Two</x-noerd::tab-panel>
            </x-noerd::tab-panels>
        BLADE);

        assertElementHasClasses($html, ['grid', 'min-h-0', 'grid-rows-1', 'pt-4']);
        assertElementHasClasses($html, ['min-h-0', 'overflow-y-auto', '-mx-6', 'px-6', '-mb-8', 'pb-8', 'flex']);

        expect($html)->toContain('x-show="$wire.someFlag"')
            // The visibility toggle lives in an Alpine :class binding, which carries
            // no static class attribute — assert its tokens instead of the literal.
            ->toContain('currentTab === 2 ?')
            ->toContain('invisible')
            ->toContain('pointer-events-none');
    });

    it('renders the page body as a single consolidated scroll container', function (): void {
        $html = Blade::render('<x-noerd::page>Body Content</x-noerd::page>');

        expect($html)->not->toMatch('/class="[^"]*"\s+class="/');
    });

    it('stretches the body into a flex column only for components with tabs', function (): void {
        $bodyClasses = ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto', 'flex', 'flex-col'];

        assertElementHasClasses(Livewire::test('noerd-test::page-chrome-detail')->html(), $bodyClasses);

        assertNoElementHasClasses(Livewire::test('noerd-test::page-chrome-list')->html(), $bodyClasses);
    });
});

/*
 | x-noerd::tab-panel clips at its top edge (overflow-y-auto), so the first form
 | row must never start flush against it — a flush input loses its top border and
 | its focus ring. Quick-create drops the block heading and replaces the theme's
 | grid padding, which is where that gap would otherwise disappear.
 */
describe('quick create spacing', function (): void {

    it('keeps the first quick-create field clear of the scroll container clip edge', function (): void {
        // Form fields resolve $errors from the request; Blade::render has none.
        view()->share('errors', new ViewErrorBag());

        $html = Blade::render(<<<'BLADE'
            <x-noerd::tab-content
                :layout="['quickCreate' => true, 'fields' => [['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'required' => true]]]"
                :modelId="null" />
        BLADE);

        assertElementHasClasses($html, ['[&>div>div]:pt-2']);
        assertNoElementHasClasses($html, ['[&>div>div]:py-0']);
    });
});
