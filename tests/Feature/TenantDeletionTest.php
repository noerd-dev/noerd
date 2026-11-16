<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Events\TenantDeleted;
use Noerd\Events\TenantDeleting;
use Noerd\Events\TenantMemberRemoved;
use Noerd\Exceptions\TenantDeletionBlockedException;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\UserSetting;
use Noerd\Services\TenantDeletionService;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->homeTenant = Tenant::factory()->create(['name' => 'Home Tenant']);
    $this->tenant = Tenant::factory()->create(['name' => 'Doomed Tenant']);

    $this->superAdmin = NoerdUser::factory()->create(['super_admin' => true, 'selected_tenant_id' => $this->homeTenant->id]);
    $this->homeTenant->addMember($this->superAdmin, Profile::Admin->value);

    $this->member = NoerdUser::factory()->create(['selected_tenant_id' => $this->tenant->id]);
    $this->tenant->addMember($this->member, Profile::Admin->value);

    TenantHelper::setSelectedTenantId($this->homeTenant->id);
    TenantHelper::setSelectedApp('SETUP');
});

describe('service', function (): void {
    it('deletes the tenant, ends every membership and keeps the accounts', function (): void {
        Event::fake([TenantMemberRemoved::class, TenantDeleting::class, TenantDeleted::class]);

        app(TenantDeletionService::class)->delete($this->tenant);

        expect(Tenant::find($this->tenant->id))->toBeNull()
            ->and(NoerdUser::find($this->member->id))->not->toBeNull()
            ->and(UserSetting::query()->where('user_id', $this->member->id)->value('selected_tenant_id'))->toBeNull();

        Event::assertDispatched(TenantMemberRemoved::class, fn(TenantMemberRemoved $event): bool => $event->tenantId === $this->tenant->id && $event->userId === $this->member->id);
        Event::assertDispatched(TenantDeleting::class, fn(TenantDeleting $event): bool => $event->tenant->is($this->tenant));
        Event::assertDispatched(TenantDeleted::class, fn(TenantDeleted $event): bool => $event->tenantId === $this->tenant->id && $event->tenantName === 'Doomed Tenant');
    });

    it('refuses while a blocker names a reason and deletes nothing', function (): void {
        $service = app(TenantDeletionService::class);
        $service->registerBlocker(fn(Tenant $tenant): ?string => $tenant->is($this->tenant) ? 'Zz has invoices.' : null);

        expect($service->blockers($this->homeTenant))->toBe([])
            ->and($service->blockers($this->tenant))->toBe(['Zz has invoices.'])
            ->and(fn() => $service->delete($this->tenant))->toThrow(TenantDeletionBlockedException::class, 'Zz has invoices.');

        expect(Tenant::find($this->tenant->id))->not->toBeNull()
            ->and($this->tenant->users()->whereKey($this->member->id)->exists())->toBeTrue();
    });

    it('rolls the whole deletion back when a module fails while the tenant is deleting', function (): void {
        Event::listen(TenantDeleting::class, function (): void {
            throw new RuntimeException('Zz cleanup failed.');
        });

        expect(fn() => app(TenantDeletionService::class)->delete($this->tenant))->toThrow(RuntimeException::class, 'Zz cleanup failed.');

        expect(Tenant::find($this->tenant->id))->not->toBeNull()
            ->and($this->tenant->users()->whereKey($this->member->id)->exists())->toBeTrue();
    });
});

describe('screens', function (): void {
    it('offers the delete button to a super admin only', function (): void {
        $this->actingAs($this->superAdmin);

        Livewire::test('noerd::tenant-detail', ['modelId' => (string) $this->tenant->id])
            ->assertSeeHtml('wire:key="delete-open"')
            ->call('delete')
            ->assertDispatched('noerdModal');

        $this->actingAs($this->member);
        TenantHelper::setSelectedTenantId($this->tenant->id);

        Livewire::test('noerd::tenant-detail', ['modelId' => (string) $this->tenant->id])
            ->assertDontSeeHtml('wire:key="delete-open"')
            ->call('delete')
            ->assertNotDispatched('noerdModal');
    });

    it('refuses the confirmation to everyone but a super admin', function (): void {
        $this->actingAs($this->member);
        TenantHelper::setSelectedTenantId($this->tenant->id);

        Livewire::test('noerd::tenant-delete-modal', ['tenantId' => $this->tenant->id])
            ->assertForbidden();
    });

    it('deletes the tenant once its name is typed', function (): void {
        $this->actingAs($this->superAdmin);

        Livewire::test('noerd::tenant-delete-modal', ['tenantId' => $this->tenant->id])
            ->set('confirmation', 'Doomed')
            ->call('deleteTenant')
            ->assertHasErrors('confirmation')
            ->set('confirmation', ' Doomed Tenant ')
            ->call('deleteTenant')
            ->assertHasNoErrors()
            ->assertRedirect(route('noerd.tenants'));

        expect(Tenant::find($this->tenant->id))->toBeNull();
    });

    it('names the blockers and deletes nothing', function (): void {
        app(TenantDeletionService::class)->registerBlocker(fn(): string => 'Zz has invoices.');
        $this->actingAs($this->superAdmin);

        Livewire::test('noerd::tenant-delete-modal', ['tenantId' => $this->tenant->id])
            ->assertSee('Zz has invoices.')
            ->set('confirmation', 'Doomed Tenant')
            ->call('deleteTenant')
            ->assertNoRedirect();

        expect(Tenant::find($this->tenant->id))->not->toBeNull();
    });

    it('keeps the tenant the super admin is working in', function (): void {
        $this->actingAs($this->superAdmin);
        TenantHelper::setSelectedTenantId($this->tenant->id);

        Livewire::test('noerd::tenant-delete-modal', ['tenantId' => $this->tenant->id])
            ->assertSee(__('You are working in this tenant. Switch to another tenant first.'))
            ->set('confirmation', 'Doomed Tenant')
            ->call('deleteTenant');

        expect(Tenant::find($this->tenant->id))->not->toBeNull();
    });
});
