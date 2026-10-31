<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | preselect() adopts a value a page seeded into the shared listFilters bucket.
 | A `{x}Selected()` method still wins when the component has one; without it
 | the value lands in the form itself and the relation field's title resolves
 | through the type registered for the column — no boilerplate handler needed.
 */

class ZzPreselectWidget extends Model
{
    public $timestamps = false;

    protected $table = 'zz_preselect_widgets';

    protected $guarded = [];
}

class ZzPreselectDetailComponent extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'zzPreselectTenantId';

    public function mount(): void
    {
        $this->initDetail();
        $this->preselect('zz_preselect_widget_id');
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-preselect-detail';
    }
}

class ZzPreselectHandlerDetailComponent extends ZzPreselectDetailComponent
{
    public ?string $handledWith = null;

    public function zzPreselectWidgetSelected(mixed $widgetId): void
    {
        $this->handledWith = (string) $widgetId;
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('zz_preselect_widgets')) {
        Schema::create('zz_preselect_widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    app(RelationFieldRegistry::class)->register('zzPreselectWidgetRelation', RelationFieldDefinition::model(
        'zz-preselect-widgets-list',
        null,
        ZzPreselectWidget::class,
    ));

    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
    $this->widget = ZzPreselectWidget::create(['name' => 'Erika Musterfrau']);
    session(['listFilters' => ['zz_preselect_widget_id' => $this->widget->id]]);
});

afterEach(function (): void {
    session()->forget('listFilters');
});

it('writes a preselected value into the form and resolves its title without a handler', function (): void {
    Livewire::test(ZzPreselectDetailComponent::class)
        ->assertSet('detailData.zz_preselect_widget_id', $this->widget->id)
        ->assertSet('relationTitles.zz_preselect_widget_id', 'Erika Musterfrau');
});

it('leaves the adoption to the {x}Selected() handler when the component has one', function (): void {
    Livewire::test(ZzPreselectHandlerDetailComponent::class)
        ->assertSet('handledWith', (string) $this->widget->id)
        ->assertSet('detailData.zz_preselect_widget_id', null);
});

it('never preselects into an existing record', function (): void {
    $tenant = Tenant::factory()->create();

    Livewire::test(ZzPreselectDetailComponent::class, ['modelId' => $tenant->id])
        ->assertSet('detailData.zz_preselect_widget_id', null);
});
