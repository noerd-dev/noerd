<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Helpers\AccessHelper;
use Noerd\Support\LivewireAction;
use Noerd\Traits\NoerdPage;
use Noerd\Traits\NoerdPositionRow;

/**
 * Enforces the object create/write/delete permission at the ACTION boundary for
 * every noerd detail/page component and every position row. The trait's own
 * store()/delete() already check canSaveObject()/canDeleteObject(), but a
 * component with a CUSTOM store()/delete() override may forget to — this hook
 * runs the same guard regardless of the override, and silently skips the
 * action (matching the trait's no-op) when the current user may not
 * save/delete the object. The hook runs BEFORE the action, so canSaveObject()
 * still sees the pre-store $modelId and correctly picks create (new record) vs.
 * write (update).
 *
 * A dispatched event (`storeDetail-{name}` → store) runs its listener inside
 * the same trigger as this hook, so $returnEarly() cannot stop it: a denied
 * event-triggered save or delete aborts the request instead.
 */
final class WriteGuardHook extends NoerdComponentHook
{
    protected const TRAITS = [NoerdPage::class, NoerdPositionRow::class];

    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        $effective = LivewireAction::effectiveMethod($this->component, (string) $method, is_array($params) ? $params : []);

        if ($effective !== 'store' && $effective !== 'delete') {
            return;
        }

        if (! $this->denies($effective)) {
            return;
        }

        if (LivewireAction::isDispatch((string) $method)) {
            abort(403);
        }

        $returnEarly();
    }

    /**
     * Whether the current user may NOT run $method on this component.
     */
    private function denies(string $method): bool
    {
        if ($this->uses(NoerdPage::class)) {
            return $method === 'store'
                ? ! $this->component->canSaveObject()
                : ! $this->component->canDeleteObject();
        }

        // A position row writes and deletes a row of its document: both are a
        // write of the position's model.
        $position = $this->component->position ?? null;

        return is_object($position) && ! AccessHelper::canWriteObject($position::class);
    }
}
