<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Noerd\Traits\NoerdPage;

/**
 * Enforces the object create/write/delete permission at the ACTION boundary for
 * every noerd detail/page component. The trait's own store()/delete() already
 * check canSaveObject()/canDeleteObject(), but a component with a CUSTOM
 * store()/delete() override may forget to — this hook runs the same guard
 * regardless of the override, and silently skips the action (matching the
 * trait's no-op) when the current user may not save/delete the object. The
 * hook runs BEFORE the action, so canSaveObject() still sees the pre-store
 * $modelId and correctly picks create (new record) vs. write (update).
 */
final class WriteGuardHook extends ComponentHook
{
    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        if ($method !== 'store' && $method !== 'delete') {
            return;
        }

        if (! in_array(NoerdPage::class, class_uses_recursive($this->component), true)) {
            return;
        }

        $denied = $method === 'store'
            ? ! $this->component->canSaveObject()
            : ! $this->component->canDeleteObject();

        if ($denied) {
            $returnEarly();
        }
    }
}
