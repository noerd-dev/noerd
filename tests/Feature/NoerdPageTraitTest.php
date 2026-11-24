<?php

declare(strict_types=1);

use Livewire\Component;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdPage;

uses(TestCase::class);

class NoerdPageFixturePage extends Component
{
    use NoerdPage;

    public function render(): string
    {
        return '<div></div>';
    }

    /** @return array<string, string> */
    public function exposedListeners(): array
    {
        return $this->getListeners();
    }

    public function exposedListComponent(): string
    {
        return $this->getListComponent();
    }

    protected function componentName(): string
    {
        return 'zz-fixture-page';
    }
}

class NoerdPageFixtureDetail extends Component
{
    use NoerdDetail;

    public function render(): string
    {
        return '<div></div>';
    }

    /** @return array<string, string> */
    public function exposedListeners(): array
    {
        return $this->getListeners();
    }

    public function exposedListComponent(): string
    {
        return $this->getListComponent();
    }

    protected function componentName(): string
    {
        return 'zz-fixture-detail';
    }
}

it('composes NoerdPage into NoerdDetail exactly once', function (): void {
    $traits = class_uses_recursive(NoerdPageFixtureDetail::class);

    expect($traits)->toContain(NoerdPage::class)
        ->toContain(NoerdDetail::class);

    // The Livewire trait hooks must exist once each — mount hook on the page
    // trait, rendering hook on the detail trait.
    expect(method_exists(NoerdPageFixtureDetail::class, 'mountNoerdPage'))->toBeTrue()
        ->and(method_exists(NoerdPageFixtureDetail::class, 'mountNoerdDetail'))->toBeFalse()
        ->and(method_exists(NoerdPageFixtureDetail::class, 'renderingNoerdDetail'))->toBeTrue()
        ->and(method_exists(NoerdPageFixturePage::class, 'renderingNoerdDetail'))->toBeFalse();
});

it('derives the list component from page, detail and list names', function (): void {
    $page = new NoerdPageFixturePage();
    $detail = new NoerdPageFixtureDetail();

    expect($page->exposedListComponent())->toBe('zz-fixtures-list')
        ->and($detail->exposedListComponent())->toBe('zz-fixtures-list');
});

it('registers only the refresh listener without an embedded detail', function (): void {
    $page = new NoerdPageFixturePage();

    expect($page->exposedListeners())->toBe([
        'refreshList-zz-fixture-page' => 'refreshList',
    ]);
});

it('registers the store roundtrip listeners when the page yaml declares a detail', function (): void {
    $page = new NoerdPageFixturePage();
    $page->pageLayout = ['detail' => 'crm::zz-fixture-detail'];

    expect($page->exposedListeners())->toBe([
        'refreshList-zz-fixture-page' => 'refreshList',
        'detailStored-crm::zz-fixture-detail' => 'embeddedDetailStored',
        'detailDataUpdated-crm::zz-fixture-detail' => 'embeddedDetailDataUpdated',
    ]);
});

it('ignores a detail entry that is not a component name', function (): void {
    $page = \Livewire\invade(new NoerdPageFixturePage());
    $page->pageLayout = ['detail' => ''];

    expect($page->embeddedDetailComponent())->toBeNull()
        ->and($page->exposedListeners())->toBe(['refreshList-zz-fixture-page' => 'refreshList']);
});

it('registers the storeDetail trigger on details', function (): void {
    $detail = new NoerdPageFixtureDetail();

    expect($detail->exposedListeners())->toBe([
        'refreshList-zz-fixture-detail' => 'refreshList',
        'storeDetail-zz-fixture-detail' => 'storeFromHost',
    ]);
});

it('resets the tab when a different component type was opened before', function (): void {
    session(['noerd.lastDetailComponent' => 'other-component']);

    $page = new NoerdPageFixturePage();
    $page->currentTab = 3;
    $page->mountNoerdPage();

    // mount() only READS the session — a modal-stack update re-mounts the
    // component, so the write belongs to the render hook.
    expect($page->currentTab)->toBe(1)
        ->and(session('noerd.lastDetailComponent'))->toBe('other-component');

    $page->renderingNoerdPage();

    expect(session('noerd.lastDetailComponent'))->toBe('zz-fixture-page');
});

it('keeps the tab when the same component type reopens', function (): void {
    session(['noerd.lastDetailComponent' => 'zz-fixture-page']);

    $page = new NoerdPageFixturePage();
    $page->currentTab = 3;
    $page->mountNoerdPage();

    expect($page->currentTab)->toBe(3);
});

it('keeps the tab a page load names even after another component type', function (): void {
    session(['noerd.lastDetailComponent' => 'other-component']);
    request()->query->set('tab', '4');

    $page = new NoerdPageFixturePage();
    $page->currentTab = 4;
    $page->mountNoerdPage();

    expect($page->currentTab)->toBe(4);
});

it('restores the tab of a routed modal reopened after a reload', function (): void {
    session(['noerd.lastDetailComponent' => 'other-component']);
    session()->flash('noerd-modal.open', [
        'component' => 'zz-fixture-page',
        'arguments' => ['modelId' => 9],
        'url' => 'https://example.test/party/9?modal=true&tab=4',
    ]);

    $page = new NoerdPageFixturePage();
    $page->modelId = 9;
    $page->mountNoerdPage();

    expect($page->currentTab)->toBe(4);
});

it('keeps its own tab when the page below a reopened routed modal is another record of the same type', function (): void {
    session(['noerd.lastDetailComponent' => 'zz-fixture-page']);
    session()->flash('noerd-modal.open', [
        'component' => 'zz-fixture-page',
        'arguments' => ['modelId' => 9],
        'url' => 'https://example.test/party/9?modal=true&tab=4',
    ]);
    request()->query->set('tab', '2');

    $page = new NoerdPageFixturePage();
    $page->modelId = 5;
    $page->currentTab = 2;
    $page->mountNoerdPage();

    expect($page->currentTab)->toBe(2);
});

it('ignores the url tab during livewire requests', function (): void {
    session(['noerd.lastDetailComponent' => 'other-component']);
    request()->query->set('tab', '4');
    request()->headers->set('X-Livewire', '1');

    $page = new NoerdPageFixturePage();
    $page->currentTab = 4;
    $page->mountNoerdPage();

    expect($page->currentTab)->toBe(1);
});

it('leaves the tab session untouched for embedded components', function (): void {
    session(['noerd.lastDetailComponent' => 'zz-fixture-page']);

    $detail = new NoerdPageFixtureDetail();
    $detail->embedded = true;
    $detail->currentTab = 2;
    $detail->mountNoerdPage();
    $detail->renderingNoerdPage();

    expect($detail->currentTab)->toBe(2)
        ->and(session('noerd.lastDetailComponent'))->toBe('zz-fixture-page');
});

it('resolves the layout theme for hand-written chrome', function (): void {
    $page = \Livewire\invade(new NoerdPageFixturePage());

    expect($page->detailTheme())->toBe('default');

    $page->pageLayout = ['theme' => 'numbered'];
    expect($page->detailTheme())->toBe('numbered');

    // A theme whose registration is gone must never break a page.
    $page->pageLayout = ['theme' => 'does-not-exist'];
    expect($page->detailTheme())->toBe('default');
});
