<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Livewire\Attributes\Computed;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\Impersonation;

/**
 * Shared target authorization and the two removal paths of the admin user
 * editor. The page (`noerd-user-page`) and the embedded form
 * (`noerd-user-detail`) are two entry points to the SAME account, so both must
 * apply the identical checks.
 *
 * An account is an identity of the whole installation; a tenant admin manages
 * only the MEMBERSHIPS of the tenants they administer. The account itself —
 * name, e-mail, password, its deletion — belongs to the person and to the
 * super admin (see canManageAccount()).
 */
trait AdministersNoerdUsers
{
    #[Computed]
    public function assignedToCurrentTenant(): bool
    {
        if (! isset($this->modelId)) {
            return false;
        }

        $user = NoerdUser::find($this->modelId);
        $tenantId = TenantHelper::getSelectedTenantId();

        if (! $user || ! $tenantId) {
            return false;
        }

        return $user->tenants()->whereKey($tenantId)->exists();
    }

    /**
     * Delete the account for good — a super admin decision. Its memberships
     * end first (so every module hears TenantMemberRemoved), then the account
     * goes. Never one's own account, never a super admin account: those are
     * withdrawn on the console.
     */
    public function deleteAccount(): void
    {
        $admin = NoerdAuth::user();

        abort_unless($admin?->isSuperAdmin(), 403);
        abort_if(! $this->modelId || (int) $this->modelId === (int) $admin->id, 403);

        $user = NoerdUser::findOrFail($this->modelId);

        abort_if($user->isSuperAdmin(), 403);

        foreach ($user->tenants()->get() as $tenant) {
            $tenant->removeMember($user);
        }

        $user->delete();

        $this->closeModalProcess($this->getListComponent());
    }

    /**
     * Whether the signed-in administrator may change the ACCOUNT (name, e-mail,
     * password): a super admin, or the person editing their own account — but
     * never an administrator signed in as the person. A tenant admin manages
     * memberships only.
     */
    protected function canManageAccount(): bool
    {
        $admin = NoerdAuth::user();

        if (! $admin) {
            return false;
        }

        if ($admin->isSuperAdmin()) {
            return true;
        }

        return $this->modelId
            && (int) $this->modelId === (int) $admin->id
            && ! Impersonation::active();
    }

    /**
     * The edited account must belong to a tenant this admin administers.
     * An admin check on mount only proves the CALLER is an admin — $modelId can
     * still be repointed on a later request (it is a plain URL-bound property).
     * An account outside the administered tenants is added through its e-mail
     * address ("Add User"), never opened.
     */
    protected function authorizeTargetUser(): void
    {
        $admin = NoerdAuth::user();

        // 'new' is the create-modal sentinel in a record URL (/setup/noerd-user/new)
        // and is normalized away by prepareRoutedModal() during mounting.
        if (! $this->modelId || $this->modelId === 'new' || $admin->isSuperAdmin()) {
            return;
        }

        $target = NoerdUser::find($this->modelId);

        abort_unless($target !== null, 404);

        // A super admin is never editable from a tenant admin's user screen.
        abort_if($target->isSuperAdmin(), 403);

        abort_unless(
            $target->tenants()->whereIn('tenants.id', $admin->adminTenants()->pluck('tenants.id'))->exists(),
            403,
        );
    }

    /**
     * End the account's membership in the selected tenant. The account stays —
     * also when this was its last tenant: it can be added again by e-mail, and
     * a super admin finds it under "Without tenant".
     */
    protected function removeFromSelectedTenant(): void
    {
        // Same target check as store(): $modelId is URL-bound and rewritable.
        $this->authorizeTargetUser();

        // An administrator never removes their own access.
        abort_if((int) $this->modelId === NoerdAuth::id(), 403);

        $tenantId = TenantHelper::getSelectedTenantId();

        abort_unless($tenantId !== null && NoerdAuth::user()->isAdmin($tenantId), 403);

        $user = NoerdUser::findOrFail($this->modelId);

        Tenant::findOrFail($tenantId)->removeMember($user);

        $this->closeModalProcess($this->getListComponent());
    }
}
