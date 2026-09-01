<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\ComponentAccessGuard;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Second-round hardening of the dynamic mount seams. Each case below was a
 | working exploit: the component name spelling that slipped past the admin
 | guard, the model repoint through mount arguments (which #[Locked] cannot
 | cover), and the app name that became a path segment.
 */

class ZzMountGuardListComponent extends Component
{
    use NoerdList;

    public $listModel = Profile::class;

    // Declared like a real list that opts into an explicit permission model —
    // Livewire only assigns mount params to properties the class defines.
    public ?string $objectPermissionModel = Profile::class;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-mount-guard-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return ['title' => 'Guard', 'columns' => [['field' => 'name', 'label' => 'Name']]];
    }
}

beforeEach(function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['super_admin' => false]);
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($user);

    expect($user->isAdmin())->toBeFalse();
});

it('guards an admin component under every spelling Livewire resolves', function (string $name): void {
    // Livewire strips the ⚡ marker, rewrites '/' to '.' and drops empty dot
    // segments when resolving the view — so all of these load the very same
    // admin component and must all be refused.
    expect(ComponentAccessGuard::allows($name))->toBeFalse("[{$name}] must stay admin-only");
})->with([
    'noerd::setup-languages-list',
    'noerd::.setup-languages-list',
    'noerd::..setup-languages-list',
    'noerd::/setup-languages-list',
    '.setup-languages-list',
    './setup-languages-list',
    'NOERD::.Setup-Languages-List',
]);

it('does not mount an admin screen through the dot-prefixed alias', function (): void {
    Livewire::test('noerd::.setup-languages-list')->assertStatus(403);
});

it('refuses a model repoint through mount arguments', function (): void {
    // #[Locked] and the update hook only veto updates; Livewire assigns mount
    // parameters to matching public properties beforehand.
    // Livewire wraps the refusal in a ViewException — assert on the cause.
    foreach (['listModel' => NoerdUser::class, 'objectPermissionModel' => Tenant::class] as $property => $value) {
        try {
            Livewire::test(ZzMountGuardListComponent::class, [$property => $value]);
            $thrown = null;
        } catch (Throwable $e) {
            $thrown = $e->getPrevious() ?? $e;
        }

        expect($thrown)->toBeInstanceOf(CannotUpdateLockedPropertyException::class, "[{$property}] must be refused at mount");
    }
});

it('still accepts the picker arguments a relation field legitimately passes', function (): void {
    Livewire::test(ZzMountGuardListComponent::class, [
        'listActionMethod' => 'selectAction',
        'context' => 'detailData.owner_id',
    ])->assertOk();
});

it('rejects an app name that is not a plain identifier', function (): void {
    TenantHelper::setSelectedApp('SETUP');

    // The selected app becomes a path segment in the config resolution.
    TenantHelper::setSelectedApp('../../../../tmp/evil');

    expect(TenantHelper::getSelectedApp())->toBe('SETUP')
        ->and(StaticConfigHelper::getCurrentApp())->toBe('setup');
});
