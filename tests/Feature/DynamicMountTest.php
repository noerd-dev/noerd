<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\ComponentAccessGuard;
use Noerd\Support\ComponentAccessHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Components reachable through the DYNAMIC mount seams — the client-dispatchable
 | `noerdModal` event and GET /noerd/component-page/{component} — receive
 | attacker-chosen mount arguments. Livewire assigns those to matching public
 | properties INCLUDING #[Locked] ones (Locked only vetoes the update path), so
 | every component that acts on such an argument must authorize the target
 | itself. Each test below is a previously working exploit.
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

/**
 * Attach $user to $tenant with an ADMIN or MEMBER profile so isAdmin() reflects
 * the intended role. Created without an active auth context, so BelongsToTenant's
 * creating hook does not re-tenant the fixtures.
 */
function zzDynamicMountTenantUser(Tenant $tenant, bool $admin = false, bool $super = false): NoerdUser
{
    $user = NoerdUser::factory()->create(['super_admin' => $super]);

    $user->tenants()->attach($tenant->id, [
        'profile_key' => $admin ? Profile::Admin->value : Profile::User->value,
    ]);

    return $user;
}

/** A non-admin of a fresh tenant, acting, with SETUP selected. */
function zzActAsNonAdmin(): NoerdUser
{
    $tenant = Tenant::factory()->create();
    $user = zzDynamicMountTenantUser($tenant, admin: false);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    expect($user->isAdmin())->toBeFalse();

    return $user;
}

describe('authorization', function (): void {
    beforeEach(function (): void {
        $this->attacker = zzActAsNonAdmin();
    });

    it('refuses to set a foreign account password from a crafted mount', function (): void {
        $victim = NoerdUser::factory()->create([
            'super_admin' => true,
            'password' => Hash::make('original-secret'),
        ]);

        try {
            Livewire::test('noerd::user-update-password', ['userId' => $victim->id])
                ->set('password', 'attacker-choice')
                ->set('password_confirmation', 'attacker-choice')
                ->call('updatePassword');
        } catch (Throwable) {
            // Either shape is fine — the password surviving is the assertion.
        }

        expect(Hash::check('original-secret', $victim->refresh()->password))->toBeTrue();
    });

    it('does not expose the password form through the generic component page', function (): void {
        $victim = NoerdUser::factory()->create();

        $this->get('/noerd/component-page/noerd::user-update-password?userId=' . $victim->id)
            ->assertForbidden();
    });

    it('never invokes a non-relation method from a relation box tile', function (): void {
        $victim = Tenant::factory()->create(['name' => 'Foreign Tenant']);

        // 'delete' exists on every model — the tile resolver used to call whatever
        // method_exists() accepted, so this deleted the record outright.
        try {
            Livewire::test('noerd::relation-box', [
                'modelClass' => Tenant::class,
                'modelId' => $victim->id,
                'relations' => [['relation' => 'delete', 'label' => 'x', 'heroicon' => 'x']],
            ]);
        } catch (Throwable) {
            // A render failure is acceptable; the record surviving is what matters.
        }

        expect(Tenant::find($victim->id))->not->toBeNull();
    });

    it('still counts a genuine relation in the relation box', function (): void {
        $tenant = Tenant::factory()->create();
        NoerdUser::factory()->create()->tenants()->attach($tenant->id);

        $component = Livewire::test('noerd::relation-box', [
            'modelClass' => Tenant::class,
            'modelId' => $tenant->id,
            'relations' => [['relation' => 'users', 'label' => 'Users', 'heroicon' => 'users']],
        ]);

        expect($component->get('resolvedRelations')[0]['count'])->toBe(1);
    });

    it('refuses to create a tenant from the inner component for a non-admin', function (): void {
        try {
            Livewire::test('noerd::create-new-tenant')
                ->set('name', 'escalation')
                ->call('createTenant');
        } catch (Throwable) {
            // Either shape is fine — no tenant and no admin grant is the assertion.
        }

        expect(Tenant::where('name', 'escalation')->exists())->toBeFalse()
            ->and(NoerdUser::find($this->attacker->id)->isAdmin())->toBeFalse();
    });

    it('keeps the admin path working for an admin of the edited user', function (): void {
        $tenant = Tenant::factory()->create();
        $admin = NoerdUser::factory()->create();
        $admin->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

        $member = NoerdUser::factory()->create(['password' => Hash::make('old')]);
        $member->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

        TenantHelper::setSelectedTenantId($tenant->id);
        $this->actingAs($admin);

        Livewire::test('noerd::user-update-password', ['userId' => $member->id])
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        expect(Hash::check('new-password', $member->refresh()->password))->toBeTrue();
    });
});

/*
 | Second-round hardening of the dynamic mount seams. Each case below was a
 | working exploit: the component name spelling that slipped past the admin
 | guard, the model repoint through mount arguments (which #[Locked] cannot
 | cover), and the app name that became a path segment.
 */
describe('hardening', function (): void {
    beforeEach(function (): void {
        zzActAsNonAdmin();
    });

    it('guards an admin component under every spelling Livewire resolves', function (string $name): void {
        // Livewire strips the ⚡ marker, rewrites '/' to '.' and drops empty dot
        // segments when resolving the view — so all of these load the very same
        // admin component and must all be refused. The un-namespaced aliases
        // exist because noerd also registers a bare component location.
        expect(ComponentAccessGuard::allows($name))->toBeFalse("[{$name}] must stay admin-only");
    })->with([
        'noerd::setup-languages-list',
        'noerd::.setup-languages-list',
        'noerd::..setup-languages-list',
        'noerd::/setup-languages-list',
        '.setup-languages-list',
        './setup-languages-list',
        'NOERD::.Setup-Languages-List',
        'tenants-list',
        'setup-languages-list',
        'setup-collection-detail',
        'NOERD::Tenants-List',
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
});

describe('component access guard', function (): void {
    beforeEach(function (): void {
        $this->tenant = Tenant::factory()->create();
        $this->actingAs(zzDynamicMountTenantUser($this->tenant, admin: false));
        TenantHelper::setSelectedTenantId($this->tenant->id);
    });

    it('denies a non-admin from mounting an admin component through the guard', function (): void {
        expect(ComponentAccessGuard::allows('noerd::noerd-user-detail'))->toBeFalse();
        expect(ComponentAccessGuard::allows('noerd::system-settings-page'))->toBeFalse();
        expect(ComponentAccessGuard::allows('noerd::tenant-detail'))->toBeFalse();

        // The same refusal, through the abort() entry point the dynamic mount uses.
        expect(fn(): mixed => ComponentAccessGuard::authorize('noerd::noerd-user-detail'))
            ->toThrow(HttpException::class);
    });

    it('enforces the admin guard from the component boot hook, regardless of mount path', function (string $name): void {
        // Any mount (route, modal stack, generic page) boots this hook — an admin
        // component with no self-guard of its own is still rejected here, so the
        // modal system needs no knowledge of noerd's authorization. Both names
        // resolve to the SAME compiled component; only the mount name differs,
        // and getName() reports whichever was used.
        $hook = new ComponentAccessHook();
        $hook->setComponent(new class ($name) {
            public function __construct(private readonly string $name) {}

            public function getName(): string
            {
                return $this->name;
            }
        });

        expect(fn(): mixed => $hook->boot())->toThrow(HttpException::class);
    })->with(['tenants-list', 'noerd::tenants-list']);

    it('lets the boot hook through for a non-admin component', function (): void {
        $hook = new ComponentAccessHook();
        $hook->setComponent(new class {
            public function getName(): string
            {
                return 'crm::accounts-list';
            }
        });

        expect(fn(): mixed => $hook->boot())->not->toThrow(HttpException::class);
    });

    it('permits an admin to mount an admin component, and anyone a non-admin component', function (): void {
        $this->actingAs(zzDynamicMountTenantUser($this->tenant, admin: true));

        expect(ComponentAccessGuard::allows('noerd::noerd-user-detail'))->toBeTrue();
        // Non-admin components are never blocked by this guard.
        expect(ComponentAccessGuard::allows('crm::accounts-list'))->toBeTrue();
        expect(ComponentAccessGuard::allows('noerd::dashboard'))->toBeTrue();
        expect(ComponentAccessGuard::allows(null))->toBeTrue();
    });

    it('honours module-registered admin components', function (): void {
        ComponentAccessGuard::registerAdminComponents(['plus::user-role-detail']);

        expect(ComponentAccessGuard::allows('plus::user-role-detail'))->toBeFalse();
    });
});

describe('audit list target validation', function (): void {
    beforeEach(function (): void {
        $this->actingAs(NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create());
    });

    it('rejects a model class that is not auditable', function (): void {
        Livewire::test('noerd::audit-list', ['modelClass' => Tenant::class, 'modelId' => 1])
            ->assertStatus(404);
    });

    it('rejects a class that is not an eloquent model', function (): void {
        Livewire::test('noerd::audit-list', ['modelClass' => Illuminate\Support\Str::class, 'modelId' => 1])
            ->assertStatus(404);
    });
});
