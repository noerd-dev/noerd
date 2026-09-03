<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | openRelationDetail() is a client-callable action, so the modal TARGET never
 | comes from the call: the field is looked up in the layout and its registered
 | relation definition supplies route and component. The layouts here are
 | synthetic — the shipped YAML is configuration and is never asserted.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());
});

function zzRegisterAccountRelation(?string $detailRoute): void
{
    app(RelationFieldRegistry::class)->register('zzAccountRelation', RelationFieldDefinition::model(
        listComponent: 'zz::accounts-list',
        detailComponent: 'zz::account-detail',
        detailRoute: $detailRoute,
    ));
}

it('opens a relation detail by the definition route with the component as fallback', function (): void {
    zzRegisterAccountRelation('zz.relation.account');

    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => 17])
        ->call('openRelationDetail', 'detailData.account_id')
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['route'] ?? null) === 'zz.relation.account'
                && ($params['modalComponent'] ?? null) === 'zz::account-detail'
                && ($params['arguments']['modelId'] ?? null) === 17,
        );
});

it('opens a relation detail by component when the definition declares no route', function (): void {
    zzRegisterAccountRelation(null);

    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => 17])
        ->call('openRelationDetail', 'detailData.account_id')
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['modalComponent'] ?? null) === 'zz::account-detail'
                && ! array_key_exists('route', $params),
        );
});

it('opens nothing when the relation field has no value', function (): void {
    zzRegisterAccountRelation('zz.relation.account');

    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => null])
        ->call('openRelationDetail', 'detailData.account_id')
        ->assertNotDispatched('noerdModal');
});

it('ignores a field name the layout does not declare', function (): void {
    zzRegisterAccountRelation('zz.relation.account');

    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['account_id' => 17, 'secret_id' => 99])
        ->call('openRelationDetail', 'detailData.secret_id')
        ->assertNotDispatched('noerdModal');
});

it('ignores a layout field that is not a registered relation type', function (): void {
    zzRegisterAccountRelation('zz.relation.account');

    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['note' => 'x'])
        ->call('openRelationDetail', 'detailData.note')
        ->assertNotDispatched('noerdModal');
});

it('finds a relation field nested in a block', function (): void {
    zzRegisterAccountRelation('zz.relation.account');

    Livewire::test(ZzRelationDetailPage::class)
        ->set('detailData', ['nested_account_id' => 21])
        ->call('openRelationDetail', 'detailData.nested_account_id')
        ->assertDispatched(
            'noerdModal',
            fn(string $event, array $params): bool => ($params['arguments']['modelId'] ?? null) === 21,
        );
});

class ZzRelationDetailPage extends Component
{
    use NoerdPage;

    public function mount(): void
    {
        $this->pageLayout = [
            'fields' => [
                ['name' => 'detailData.account_id', 'label' => 'Account', 'type' => 'zzAccountRelation'],
                ['name' => 'detailData.note', 'label' => 'Note', 'type' => 'text'],
                ['type' => 'block', 'fields' => [
                    ['name' => 'detailData.nested_account_id', 'label' => 'Nested', 'type' => 'zzAccountRelation'],
                ]],
            ],
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-relation-detail-page';
    }
}
