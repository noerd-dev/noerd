<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Livewire\Hooks\EmbeddedDetailHostHook;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\ComponentNaming;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The list ↔ record naming convention lives in ComponentNaming, so a list and
 | the relation field waiting for its selection derive the same names; and the
 | store roundtrip events between a page and its embedded detail are bound to
 | that page. Synthetic fixtures only.
 */

class ZzNamingDeclaredList extends Component
{
    use NoerdList;

    public $detailComponent = 'zz-module::zz-gadget-detail';

    public function render(): string
    {
        return '<div></div>';
    }

    public function exposedPairedDetail(): ?string
    {
        return $this->pairedDetailComponent();
    }

    protected function componentName(): string
    {
        return 'zz-module::zz-widgets-list';
    }
}

class ZzHostPage extends Component
{
    use NoerdPage;

    public $detailModel = Tenant::class;

    public function mount(): void
    {
        $this->initPage();
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-host-page';
    }
}

/** An embedded detail; the test plays the hook's part and names its host. */
class ZzHostDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function mount(?string $zzHost = null): void
    {
        $this->initDetail();
        $this->hostId = $zzHost;
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-host-detail';
    }

    protected function additionalDetailFields(): array
    {
        return ['detailData.name'];
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-host-page', ZzHostPage::class);
    Livewire::component('zz-host-detail', ZzHostDetail::class);
});

describe('ComponentNaming', function (): void {
    it('takes only the last -list as the suffix', function (): void {
        expect(ComponentNaming::listEntity('push::push-distribution-lists-list'))->toBe('push-distribution-list')
            ->and(ComponentNaming::selectEvent('push::push-distribution-lists-list'))->toBe('pushDistributionListSelected')
            ->and(ComponentNaming::deepLinkParam('crm::accounts-list'))->toBe('accountId');
    });

    it('drops namespace and subfolder for the entity but keeps them for the paired detail', function (): void {
        expect(ComponentNaming::bareName('booking-members::stamp-cards.customer-stamp-cards-list'))->toBe('customer-stamp-cards-list')
            ->and(ComponentNaming::pairedDetail('booking-members::stamp-cards.customer-stamp-cards-list'))
            ->toBe('booking-members::stamp-cards.customer-stamp-card-detail')
            ->and(ComponentNaming::pairedDetail('zz-widget-detail'))->toBeNull();
    });

    it('gives a relation field the event its list dispatches, also for a dotted list', function (): void {
        $definition = RelationFieldDefinition::model(
            listComponent: 'booking-members::stamp-cards.customer-stamp-cards-list',
            modelClass: null,
            titleResolver: 'name',
        );

        expect($definition->getSelectEvent())->toBe('customerStampCardSelected')
            ->and($definition->getSelectEvent())
            ->toBe(ComponentNaming::selectEvent('booking-members::stamp-cards.customer-stamp-cards-list'));
    });

    it('takes the picklist badges from the declared detail', function (): void {
        expect((new ZzNamingDeclaredList())->exposedPairedDetail())->toBe('zz-module::zz-gadget-detail');
    });
});

describe('embedded detail host', function (): void {
    it('binds an embedded detail to the page that renders it', function (): void {
        $page = new ZzHostPage();
        $page->setId('page-a');
        $detail = new ZzHostDetail();

        $hook = new EmbeddedDetailHostHook();
        $hook->setComponent($detail);
        $hook->mount(['embedded' => true], $page);

        expect($detail->hostId)->toBe('page-a');
    });

    it('leaves a detail that is not embedded unbound', function (): void {
        $page = new ZzHostPage();
        $page->setId('page-a');
        $detail = new ZzHostDetail();

        $hook = new EmbeddedDetailHostHook();
        $hook->setComponent($detail);
        $hook->mount([], $page);

        expect($detail->hostId)->toBeNull();
    });

    it('saves only on the command of its own page', function (): void {
        $tenant = Tenant::factory()->create(['name' => 'Original']);

        Livewire::test('zz-host-detail', ['modelId' => $tenant->id, 'zzHost' => 'page-a'])
            ->set('detailData.name', 'From another page')
            ->dispatch('storeDetail-zz-host-detail', host: 'page-b');

        expect($tenant->refresh()->name)->toBe('Original');

        Livewire::test('zz-host-detail', ['modelId' => $tenant->id, 'zzHost' => 'page-a'])
            ->set('detailData.name', 'From its page')
            ->dispatch('storeDetail-zz-host-detail', host: 'page-a');

        expect($tenant->refresh()->name)->toBe('From its page');
    });

    it('adopts only the record its own detail reports', function (): void {
        $tenant = Tenant::factory()->create();

        $page = Livewire::test('zz-host-page');

        $page->call('embeddedDetailStored', $tenant->id, 'page-of-someone-else')
            ->assertSet('modelId', null);

        $page->call('embeddedDetailStored', $tenant->id, $page->id())
            ->assertSet('modelId', $tenant->id);
    });

    it('reports the host with the stored record', function (): void {
        $tenant = Tenant::factory()->create();

        Livewire::test('zz-host-detail', ['modelId' => $tenant->id, 'zzHost' => 'page-a'])
            ->call('store')
            ->assertDispatched('detailStored-zz-host-detail', modelId: $tenant->id, host: 'page-a');
    });
});
