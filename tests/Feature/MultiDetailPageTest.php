<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A page embedding SEVERAL details (`detail:` + `details:` in the page YAML): one
 | Save fans out to every detail, and the reports coming back are told apart by the
 | `detail` payload key — only the primary detail's record is the page's own.
 */

class ZzMultiDetailPage extends Component
{
    use NoerdPage;

    /** @var array<int, array<string, mixed>> */
    public array $additionalStored = [];

    /** @var array<int, array<string, mixed>> */
    public array $additionalUpdated = [];

    /** @param  array<string, mixed>  $pageLayout */
    public function mount(array $pageLayout = []): void
    {
        // $pageLayout is a locked property (LockedPropertiesHook) — seeded on mount.
        $this->pageLayout = $pageLayout;
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-multi-detail-page';
    }

    protected function afterAdditionalDetailStored(string $detail, int $modelId): void
    {
        $this->additionalStored[] = ['detail' => $detail, 'modelId' => $modelId];
    }

    protected function afterAdditionalDetailDataUpdated(string $detail, array $detailData): void
    {
        $this->additionalUpdated[] = ['detail' => $detail, 'detailData' => $detailData];
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-multi-detail-page', ZzMultiDetailPage::class);
});

function multiDetailPage(): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test('zz-multi-detail-page', [
        'pageLayout' => ['detail' => 'zz::primary-detail', 'details' => ['zz::second-detail']],
    ]);
}

it('dispatches the store trigger to every embedded detail', function (): void {
    multiDetailPage()
        ->call('store')
        ->assertDispatched('storeDetail-zz::primary-detail')
        ->assertDispatched('storeDetail-zz::second-detail');
});

it('adopts the id reported by the primary detail — with or without the detail key', function (): void {
    multiDetailPage()
        ->call('embeddedDetailStored', 7, 'zz::primary-detail')
        ->assertSet('modelId', 7)
        ->assertSet('showSuccessIndicator', false)
        ->call('embeddedDetailStored', 8)
        ->assertSet('modelId', 8)
        ->assertSet('additionalStored', []);
});

it('neither adopts the id nor merges the data of an additional detail, but runs the hooks', function (): void {
    multiDetailPage()
        ->set('detailData', ['name' => 'Primary'])
        ->call('embeddedDetailStored', 42, 'zz::second-detail')
        ->assertSet('modelId', null)
        ->assertSet('showSuccessIndicator', true)
        ->assertSet('additionalStored', [['detail' => 'zz::second-detail', 'modelId' => 42]])
        ->call('embeddedDetailDataUpdated', ['name' => 'Second'], 'zz::second-detail')
        ->assertSet('detailData', ['name' => 'Primary'])
        ->assertSet('additionalUpdated', [['detail' => 'zz::second-detail', 'detailData' => ['name' => 'Second']]]);
});

it('merges the form state of the primary detail into the page mirror', function (): void {
    multiDetailPage()
        ->set('detailData', ['name' => 'Primary', 'groups' => ['a']])
        ->call('embeddedDetailDataUpdated', ['name' => 'Changed'], 'zz::primary-detail')
        ->assertSet('detailData', ['name' => 'Changed', 'groups' => ['a']])
        ->call('embeddedDetailDataUpdated', ['email' => 'x@example.com'])
        ->assertSet('detailData', ['name' => 'Changed', 'groups' => ['a'], 'email' => 'x@example.com'])
        ->assertSet('additionalUpdated', []);
});

it('treats a detail the page does not embed as the primary report', function (): void {
    // A stray or older detail without a matching `details:` entry must not be
    // swallowed silently — the single-detail behaviour applies.
    multiDetailPage()
        ->call('embeddedDetailStored', 9, 'zz::unknown-detail')
        ->assertSet('modelId', 9)
        ->assertSet('additionalStored', []);
});
