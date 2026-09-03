<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\ComponentHookRegistry;
use Livewire\Livewire;
use Noerd\Contracts\DeclaresRelationForms;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Support\RelationFormDefinition;
use Noerd\Support\RelationFormPersistHook;
use Noerd\Support\RelationFormSync;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

class ZzRelationChild extends Model
{
    protected $table = 'zz_relation_children';

    protected $guarded = [];
}

class ZzRelationHost extends Model implements DeclaresRelationForms
{
    protected $table = 'zz_relation_hosts';

    protected $guarded = [];

    public static function relationForms(): array
    {
        return [
            'zzAddress' => RelationFormDefinition::make(
                relation: 'zzChild',
                fields: ['line_1', 'city'],
            ),
        ];
    }

    public function zzChild(): BelongsTo
    {
        return $this->belongsTo(ZzRelationChild::class, 'zz_child_id');
    }
}

class ZzRelationRulesHost extends ZzRelationHost
{
    public static function relationForms(): array
    {
        return [
            'zzAddress' => RelationFormDefinition::make(
                relation: 'zzChild',
                fields: ['line_1', 'city'],
            )->validateUsing([
                'line_1' => ['required', 'string'],
            ]),
        ];
    }
}

class ZzRelationSpyHost extends ZzRelationHost
{
    public static ?array $spy = null;

    public static function relationForms(): array
    {
        return [
            'zzAddress' => RelationFormDefinition::make(
                relation: 'zzChild',
                fields: ['line_1', 'city'],
            )
                ->persistWhen(fn(array $data): bool => ($data['line_1'] ?? '') !== 'skip-me')
                ->persistUsing(function (Model $owner, array $data): void {
                    self::$spy = ['owner_id' => $owner->id, 'data' => $data];
                }),
        ];
    }
}

function zzRelationLayout(bool $required = false): array
{
    return ['fields' => [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text'],
        ['name' => 'detailData.zzAddress.line_1', 'label' => 'Line 1', 'type' => 'text', 'required' => $required],
        ['name' => 'detailData.zzAddress.city', 'label' => 'City', 'type' => 'text'],
    ]];
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());

    Schema::create('zz_relation_children', function (Blueprint $table): void {
        $table->id();
        $table->string('line_1')->nullable();
        $table->string('city')->nullable();
        $table->timestamps();
    });

    Schema::create('zz_relation_hosts', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->foreignId('zz_child_id')->nullable();
        $table->timestamps();
    });

    ZzRelationSpyHost::$spy = null;

    Livewire::component('zz-relation-host-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = ZzRelationHost::class;

        public ?string $detailPrimary = 'zzHostId';

        public function mount(): void
        {
            // Synthetic layout instead of a YAML file — mechanics only.
            $this->pageLayout = zzRelationLayout();
        }

        public function render(): string
        {
            return '<div>zz-relation-host</div>';
        }
    });
});

afterEach(function (): void {
    Schema::dropIfExists('zz_relation_hosts');
    Schema::dropIfExists('zz_relation_children');
});

it('hydrates a declared relation form on mount', function (): void {
    $child = ZzRelationChild::create(['line_1' => 'Musterweg 1', 'city' => 'Berlin']);
    $host = ZzRelationHost::create(['name' => 'Host', 'zz_child_id' => $child->id]);

    Livewire::test('zz-relation-host-detail', ['modelId' => $host->id])
        ->assertSet('detailData.zzAddress.line_1', 'Musterweg 1')
        ->assertSet('detailData.zzAddress.city', 'Berlin');
});

it('guarantees an empty form array for a new record', function (): void {
    Livewire::test('zz-relation-host-detail')
        ->assertSet('detailData.zzAddress', ['line_1' => null, 'city' => null]);
});

it('strips the form on default store and persists via the default persister', function (): void {
    $host = ZzRelationHost::create(['name' => 'Host']);

    Livewire::test('zz-relation-host-detail', ['modelId' => $host->id])
        ->set('detailData.name', 'Renamed')
        ->set('detailData.zzAddress.line_1', 'Neuweg 2')
        ->set('detailData.zzAddress.city', 'Hamburg')
        ->call('store')
        ->assertHasNoErrors();

    $host->refresh();
    expect($host->name)->toBe('Renamed');
    expect($host->zzChild)->not->toBeNull();
    expect($host->zzChild->line_1)->toBe('Neuweg 2');
    expect($host->zzChild->city)->toBe('Hamburg');
});

it('updates the existing related record instead of creating a new one', function (): void {
    $child = ZzRelationChild::create(['line_1' => 'Alt 1', 'city' => 'Berlin']);
    $host = ZzRelationHost::create(['name' => 'Host', 'zz_child_id' => $child->id]);

    Livewire::test('zz-relation-host-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.line_1', 'Neu 2')
        ->call('store')
        ->assertHasNoErrors();

    expect(ZzRelationChild::count())->toBe(1);
    expect($child->refresh()->line_1)->toBe('Neu 2');
});

it('keeps the form filled after store (rehydrate)', function (): void {
    $host = ZzRelationHost::create(['name' => 'Host']);

    Livewire::test('zz-relation-host-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.line_1', 'Bleibtstraße 3')
        ->call('store')
        ->assertHasNoErrors()
        ->assertSet('detailData.zzAddress.line_1', 'Bleibtstraße 3');
});

it('uses a custom persistUsing closure and honors persistWhen', function (): void {
    Livewire::component('zz-relation-spy-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = ZzRelationSpyHost::class;

        public ?string $detailPrimary = 'zzSpyId';

        public function mount(): void
        {
            $this->pageLayout = zzRelationLayout();
        }

        public function render(): string
        {
            return '<div>zz-relation-spy</div>';
        }
    });

    $host = ZzRelationSpyHost::create(['name' => 'Spy']);

    Livewire::test('zz-relation-spy-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.line_1', 'Spystraße 1')
        ->call('store')
        ->assertHasNoErrors();

    expect(ZzRelationSpyHost::$spy)->not->toBeNull();
    expect(ZzRelationSpyHost::$spy['owner_id'])->toBe($host->id);
    expect(ZzRelationSpyHost::$spy['data']['line_1'])->toBe('Spystraße 1');
    expect(ZzRelationChild::count())->toBe(0);

    ZzRelationSpyHost::$spy = null;

    Livewire::test('zz-relation-spy-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.line_1', 'skip-me')
        ->call('store')
        ->assertHasNoErrors();

    expect(ZzRelationSpyHost::$spy)->toBeNull();
});

it('does not persist the relation form for a write-denied user', function (): void {
    // Covers the canWriteObject() recheck at the save boundary (DetailSaveHook):
    // a denied user's store writes neither the host nor the related record.
    Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $host = ZzRelationHost::create(['name' => 'Host']);

    Livewire::test('zz-relation-host-detail', ['modelId' => $host->id])
        ->set('detailData.name', 'Denied Rename')
        ->set('detailData.zzAddress.line_1', 'Denied 1')
        ->call('store');

    expect($host->refresh()->name)->toBe('Host')
        ->and($host->zz_child_id)->toBeNull()
        ->and(ZzRelationChild::count())->toBe(0);
});

it('does not persist when the active layout omits the form', function (): void {
    $child = ZzRelationChild::create(['line_1' => 'Original', 'city' => 'Berlin']);
    $host = ZzRelationHost::create(['name' => 'Host', 'zz_child_id' => $child->id]);

    $component = Livewire::test('zz-relation-host-detail', ['modelId' => $host->id]);

    // Simulate a layout override that removed the form fields; the stale
    // hydrated values must not be written back. $pageLayout is locked against
    // client updates, so the override is applied server-side — exactly where a
    // real layout override resolver applies it.
    $instance = $component->instance();
    $instance->pageLayout = ['fields' => [['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text']]];
    $instance->detailData['zzAddress']['line_1'] = 'Stale Value';
    $instance->store();

    expect($child->refresh()->line_1)->toBe('Original');
});

it('does not persist after failed validation', function (): void {
    Livewire::component('zz-relation-required-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = ZzRelationHost::class;

        public ?string $detailPrimary = 'zzReqId';

        public function mount(): void
        {
            $this->pageLayout = zzRelationLayout(required: true);
        }

        public function render(): string
        {
            return '<div>zz-relation-required</div>';
        }
    });

    $host = ZzRelationHost::create(['name' => 'Host']);

    Livewire::test('zz-relation-required-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.city', 'Nur Stadt')
        ->call('store')
        ->assertHasErrors(['detailData.zzAddress.line_1']);

    expect(ZzRelationChild::count())->toBe(0);
});

it('persists on an event-triggered save (dispatch path)', function (): void {
    $host = ZzRelationHost::create(['name' => 'Host']);

    Livewire::test('zz-relation-host-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.line_1', 'Eventweg 4')
        ->dispatch('storeDetail-zz-relation-host-detail')
        ->assertHasNoErrors();

    expect($host->refresh()->zzChild?->line_1)->toBe('Eventweg 4');
});

it('applies validateUsing rules only when the form carries data', function (): void {
    Livewire::component('zz-relation-rules-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = ZzRelationRulesHost::class;

        public ?string $detailPrimary = 'zzRulesId';

        public function mount(): void
        {
            $this->pageLayout = zzRelationLayout();
        }

        public function render(): string
        {
            return '<div>zz-relation-rules</div>';
        }
    });

    $host = ZzRelationRulesHost::create(['name' => 'Host']);

    // Empty form — conditional rules must not fire.
    Livewire::test('zz-relation-rules-detail', ['modelId' => $host->id])
        ->call('store')
        ->assertHasNoErrors();

    // Partial input — city set, line_1 required by validateUsing → error, no child.
    Livewire::test('zz-relation-rules-detail', ['modelId' => $host->id])
        ->set('detailData.zzAddress.city', 'Berlin')
        ->call('store')
        ->assertHasErrors(['detailData.zzAddress.line_1']);

    expect(ZzRelationChild::count())->toBe(0);
});

it('rendered() recurses into blocks and strip() removes form and snake relation keys', function (): void {
    $blockLayout = [
        ['type' => 'block', 'fields' => [
            ['name' => 'detailData.zzAddress.line_1', 'type' => 'text'],
        ]],
    ];

    expect(RelationFormSync::rendered($blockLayout, 'zzAddress'))->toBeTrue();
    expect(RelationFormSync::rendered($blockLayout, 'other'))->toBeFalse();

    $stripped = RelationFormSync::strip(ZzRelationHost::class, [
        'name' => 'Host',
        'zzAddress' => ['line_1' => 'x'],
        'zz_child' => ['id' => 1],
    ]);

    expect($stripped)->toBe(['name' => 'Host']);
});

describe('RelationFormPersistHook', function (): void {
    it('is registered as a global Livewire component hook', function (): void {
        $registered = (new ReflectionProperty(ComponentHookRegistry::class, 'componentHooks'))->getValue();

        expect($registered)->toContain(RelationFormPersistHook::class);
    });

    it('persists the form after a hand-rolled store() that never touches relation code', function (): void {
        Livewire::component('zz-relation-manual-detail', new class extends Component {
            use NoerdDetail;

            public $detailModel = ZzRelationHost::class;

            public ?string $detailPrimary = 'zzManualId';

            public function mount(): void
            {
                $this->initDetail();
                $this->pageLayout = zzRelationLayout();
            }

            /**
             * A deliberately naive store: it builds its payload with strip()
             * and saves the model — no hydrate, no persist, no rehydrate. The
             * hook has to supply all of that.
             */
            public function store(): void
            {
                $model = ZzRelationHost::findOrNew($this->modelId);
                $model->fill(RelationFormSync::strip(ZzRelationHost::class, $this->detailData));
                $model->save();

                $this->storeProcess($model);
            }

            public function render(): string
            {
                return '<div>zz-relation-manual</div>';
            }
        });

        $host = ZzRelationHost::create(['name' => 'Host']);

        Livewire::test('zz-relation-manual-detail', ['modelId' => $host->id])
            ->set('detailData.zzAddress.line_1', 'Hookweg 4')
            ->set('detailData.zzAddress.city', 'Bremen')
            ->call('store')
            ->assertHasNoErrors()
            // rehydrated by the hook, in the same response
            ->assertSet('detailData.zzAddress.line_1', 'Hookweg 4');

        $host->refresh();
        expect($host->zzChild)->not->toBeNull()
            ->and($host->zzChild->line_1)->toBe('Hookweg 4')
            ->and($host->zzChild->city)->toBe('Bremen');
    });
});
