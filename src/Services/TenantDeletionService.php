<?php

declare(strict_types=1);

namespace Noerd\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Noerd\Events\TenantDeleted;
use Noerd\Events\TenantDeleting;
use Noerd\Exceptions\TenantDeletionBlockedException;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\UserSetting;

/**
 * Deletes a tenant with everything that belongs to it — the ONE way a tenant
 * row goes. Only a super admin may start it; the caller checks that.
 *
 * A module that owns records which must outlive the tenant (invoices under a
 * retention period, a live domain at a registrar) registers a blocker in its
 * ServiceProvider's boot():
 *
 *     app(TenantDeletionService::class)->registerBlocker(
 *         fn(Tenant $tenant): ?string => $hasInvoices ? __('The tenant has invoices.') : null,
 *     );
 *
 * The deletion ends every membership through Tenant::removeMember() (the
 * modules hear TenantMemberRemoved; the accounts stay), clears every account's
 * selection of the tenant, fires TenantDeleting and deletes the row — every
 * table with a cascading foreign key follows — in one transaction, then fires
 * TenantDeleted once it committed.
 */
final class TenantDeletionService
{
    /** @var list<Closure(Tenant): ?string> */
    private array $blockers = [];

    /**
     * @param  Closure(Tenant): ?string  $blocker  the translated reason, or null when the tenant may go
     */
    public function registerBlocker(Closure $blocker): void
    {
        $this->blockers[] = $blocker;
    }

    /**
     * The reasons that keep the tenant from being deleted — empty when it may go.
     *
     * @return list<string>
     */
    public function blockers(Tenant $tenant): array
    {
        $reasons = [];

        foreach ($this->blockers as $blocker) {
            $reason = $blocker($tenant);

            if (is_string($reason) && $reason !== '') {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * @throws TenantDeletionBlockedException when a blocker answers with a reason
     */
    public function delete(Tenant $tenant): void
    {
        $reasons = $this->blockers($tenant);

        if ($reasons !== []) {
            throw new TenantDeletionBlockedException($reasons);
        }

        $tenantId = (int) $tenant->getKey();
        $tenantName = (string) $tenant->name;

        DB::transaction(function () use ($tenant, $tenantId, $tenantName): void {
            $members = NoerdUser::query()
                ->whereHas('tenants', fn(Builder $query): Builder => $query->whereKey($tenantId))
                ->get();

            foreach ($members as $member) {
                $tenant->removeMember($member);
            }

            UserSetting::query()
                ->where('selected_tenant_id', $tenantId)
                ->update(['selected_tenant_id' => null]);

            TenantDeleting::dispatch($tenant);

            $tenant->delete();

            DB::afterCommit(fn() => TenantDeleted::dispatch($tenantId, $tenantName));
        });
    }
}
