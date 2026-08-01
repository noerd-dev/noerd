<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);

    // Synthetic detail: mechanics only, no YAML/model mounting.
    Livewire::component('zz-primary-test-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = Tenant::class;

        public ?string $detailPrimary = 'zzTestId';

        public function mount(): void {}

        public function render(): string
        {
            return '<div>zz-primary-test</div>';
        }
    });
});

it('binds modelId to the detailPrimary alias from the query string', function (): void {
    $component = Livewire::withUrlParams(['zzTestId' => 7])
        ->test('zz-primary-test-detail')
        ->assertSet('modelId', 7);

    expect($component->instance()->queryStringNoerdPage())->toBe([
        'modelId' => ['as' => 'zzTestId', 'keep' => false, 'except' => ''],
    ]);
});

it('ignores the query param when embedded — the mount argument wins', function (): void {
    $component = Livewire::withUrlParams(['zzTestId' => 999])
        ->test('zz-primary-test-detail', ['modelId' => 5, 'embedded' => true])
        ->assertSet('modelId', 5);

    expect($component->instance()->queryStringNoerdPage())->toBe([]);
});

it('binds nothing when detailPrimary is null', function (): void {
    Livewire::component('zz-primary-null-component', new class extends Component {
        use NoerdDetail;

        public function mount(): void {}

        public function render(): string
        {
            return '<div>zz-primary-null</div>';
        }
    });

    $component = Livewire::withUrlParams(['zzTestId' => 9, 'id' => 4])
        ->test('zz-primary-null-component')
        ->assertSet('modelId', null);

    expect($component->instance()->queryStringNoerdPage())->toBe([]);
});

it('throws for a model-backed detail without a detailPrimary declaration', function (): void {
    Livewire::component('zz-enforce-test-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = Tenant::class;

        public function mount(): void {}

        public function render(): string
        {
            return '<div>zz-enforce</div>';
        }
    });

    // The RuntimeException surfaces wrapped in a ViewException — match by message.
    expect(fn() => Livewire::test('zz-enforce-test-detail'))
        ->toThrow(Exception::class, 'must declare its URL alias');
});

it('does not throw for a model-backed page component without a detailPrimary', function (): void {
    Livewire::component('zz-enforce-test-page', new class extends Component {
        use NoerdDetail;

        public $detailModel = Tenant::class;

        public function mount(): void {}

        public function render(): string
        {
            return '<div>zz-enforce-page</div>';
        }
    });

    Livewire::test('zz-enforce-test-page')->assertOk();
});

it('keeps queryStringNoerdPage public for the modal clearParams discovery', function (): void {
    // noerd-modal's resolveUrlParameters() finds trait query strings via
    // get_class_methods(), which only lists public methods.
    $method = new ReflectionMethod(\Noerd\Traits\NoerdPage::class, 'queryStringNoerdPage');

    expect($method->isPublic())->toBeTrue();
});
