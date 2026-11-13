<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

class ZzSaveActionDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public bool $saved = false;

    public function save(): void
    {
        $this->saved = true;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

function zzHardeningAdmin(): NoerdUser
{
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    return $user;
}

it('refuses a language code that is no language tag', function (string $code): void {
    zzHardeningAdmin();

    Livewire::test('noerd::setup-language-detail')
        ->set('detailData.code', $code)
        ->set('detailData.name', 'Evil')
        ->call('store')
        ->assertHasErrors(['detailData.code']);

    expect(SetupLanguage::where('name', 'Evil')->exists())->toBeFalse();
})->with(["x');alert(1);('", 'de/..', 'a,b', '']);

it('renders a language code as a script literal in the switcher', function (): void {
    $user = zzHardeningAdmin();
    SetupLanguage::query()->where('tenant_id', $user->selected_tenant_id)->where('is_default', false)->first()
        ?->forceFill(['code' => "x'y", 'is_active' => true])->save();

    Livewire::test('noerd::setup-language-switcher')
        ->assertDontSeeHtml("setLanguage('x&#039;y')")
        ->assertSeeHtml("setLanguage('x\\u0027y')");
});

it('enforces the new-tenant switch on the form itself', function (): void {
    zzHardeningAdmin();
    config(['noerd.features.new_tenant' => false]);

    Livewire::test('noerd::create-tenant-form')->assertForbidden();
});

it('guards a save action like store', function (): void {
    zzHardeningAdmin();
    Gate::define(AccessHelper::OBJECT_CREATE_GATE, fn(): bool => false);

    Livewire::test(ZzSaveActionDetail::class)->call('save')->assertSet('saved', false);
});

it('runs a save action for a user who may create the object', function (): void {
    zzHardeningAdmin();

    Livewire::test(ZzSaveActionDetail::class)->call('save')->assertSet('saved', true);
});

it('never hands one tenant the select options memoized for another', function (): void {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    foreach ([[$first, 'Alpha'], [$second, 'Beta']] as [$tenant, $label]) {
        TenantContext::run($tenant->id, function () use ($tenant, $label): void {
            $collection = SetupCollection::create(['tenant_id' => $tenant->id, 'collection_key' => 'ZZ_MEMO', 'name' => 'Memo', 'sort' => 0]);
            SetupCollectionEntry::create(['tenant_id' => $tenant->id, 'setup_collection_id' => $collection->id, 'data' => ['name' => $label], 'sort' => 0]);
        });
    }

    $labels = fn(Tenant $tenant): array => TenantContext::run(
        $tenant->id,
        fn(): array => array_column(SetupCollectionHelper::selectOptions('ZZ_MEMO'), 'label'),
    );

    expect($labels($first))->toBe(['Alpha'])
        ->and($labels($second))->toBe(['Beta']);
});

it('counts nothing in the relation box of a model the user may not read', function (): void {
    $user = zzHardeningAdmin();
    Gate::define(AccessHelper::OBJECT_READ_GATE, fn(): bool => false);

    Livewire::test('noerd::relation-box', [
        'modelClass' => Tenant::class,
        'modelId' => $user->selected_tenant_id,
        'relations' => [['label' => 'Users', 'relation' => 'users', 'component' => 'noerd::noerd-users-list']],
    ])->assertSet('resolvedRelations', []);
});

it('locks the login of one address after failures on many accounts', function (): void {
    RateLimiter::clear('noerd-login|127.0.0.1');

    foreach (range(1, 20) as $i) {
        Livewire::test('noerd::auth.login')
            ->set('email', "nobody{$i}@example.test")
            ->set('password', 'wrong')
            ->call('login');
    }

    $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    RateLimiter::clear('noerd-login|127.0.0.1');
});

it('limits the reset mails one address may request', function (): void {
    RateLimiter::clear('noerd-password-reset|127.0.0.1');

    foreach (range(1, 5) as $i) {
        Livewire::test('noerd::auth.forgot-password')
            ->set('email', "nobody{$i}@example.test")
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();
    }

    Livewire::test('noerd::auth.forgot-password')
        ->set('email', 'nobody6@example.test')
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email']);

    RateLimiter::clear('noerd-password-reset|127.0.0.1');
});
