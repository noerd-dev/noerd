<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Livewire\Attributes\Computed;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;

/**
 * Shared target authorization and deletion for the admin user editor. The page
 * (`noerd-user-page`) and the embedded form (`noerd-user-detail`) are two entry
 * points to the SAME account, so both must apply the identical checks.
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
        if (! $user) {
            return false;
        }

        return $user->tenants->contains(NoerdAuth::user()->selected_tenant_id);
    }

    /**
     * The edited account must belong to a tenant this admin administers.
     * An admin check on mount only proves the CALLER is an admin — $modelId can
     * still be repointed on a later request (it is a plain URL-bound property).
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

        $adminTenantIds = $admin->adminTenants()->pluck('tenants.id');

        // Editable when the account belongs to a tenant this admin administers —
        // or when it belongs to no tenant at all (an unassigned account being
        // granted its first access from this screen).
        abort_unless(
            $target->tenants()->count() === 0
                || $target->tenants()->whereIn('tenants.id', $adminTenantIds)->exists(),
            403,
        );
    }

    /**
     * Detach the account from the current tenant and drop it entirely once no
     * tenant is left.
     */
    protected function deleteUserAccount(): void
    {
        // Same target check as store(): $modelId is URL-bound and rewritable, so
        // a mount-time admin check alone let any admin delete an account of
        // another tenant — or any account with no tenants left at all.
        $this->authorizeTargetUser();

        $user = NoerdUser::findOrFail($this->modelId);

        $user->tenants()->detach(NoerdAuth::user()->selected_tenant_id);
        $this->closeModalProcess($this->getListComponent());

        // If user has no more tenants, delete the user
        if ($user->tenants()->count() === 0) {
            $user->delete();
        }
    }
}
