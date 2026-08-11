<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());
});

it('opens a relation detail by route with the component as fallback', function (): void {
    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => 17])
        ->call('openRelationDetail', 'zz::account-detail', 'detailData.account_id', 'zz.relation.account')
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['route'] ?? null) === 'zz.relation.account'
                && ($params['modalComponent'] ?? null) === 'zz::account-detail'
                && ($params['arguments']['modelId'] ?? null) === 17,
        );
});

it('opens a relation detail by component when no route is given', function (): void {
    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => 17])
        ->call('openRelationDetail', 'zz::account-detail', 'detailData.account_id')
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['modalComponent'] ?? null) === 'zz::account-detail'
                && ! array_key_exists('route', $params),
        );
});

it('opens nothing when the relation field has no value', function (): void {
    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => null])
        ->call('openRelationDetail', 'zz::account-detail', 'detailData.account_id', 'zz.relation.account')
        ->assertNotDispatched('noerdModal');
});

class ZzRelationDetailPage extends Component
{
    use NoerdPage;

    public function render(): string
    {
        return '<div></div>';
    }

    public function getName(): string
    {
        return 'zz-relation-detail-page';
    }
}
