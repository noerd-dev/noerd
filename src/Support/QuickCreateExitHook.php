<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Noerd\Traits\NoerdDetail;

/**
 * Makes the quick-create exit a framework default for every detail component,
 * so it no longer has to be wired per component.
 *
 * Quick-create is a narrow modal for NEW records only (see the NoerdDetail trait).
 * Historically a detail's store() had to call storeProcess() to switch the modal
 * out of quick-create mode after saving; the ~45 details that hand-roll their
 * store() never did, so their modal stayed narrow with only the required fields.
 *
 * This global Livewire ComponentHook runs the exit after EVERY action: its call()
 * returns a finish callback that fires once the wrapped action has run — after the
 * action set $modelId, but before the view is rendered. Mutating $pageLayout here
 * therefore still reaches the rendered HTML (a rendering/dehydrate hook would run
 * too late for that), so the full detail renders in the SAME response, and the
 * dispatched resizeTopModal is still collected into the response effects to widen
 * the modal panel. It is a no-op for non-detail components and for details that
 * already left quick-create (e.g. via storeProcess()).
 */
class QuickCreateExitHook extends ComponentHook
{
    public function call($method, $params, $returnEarly, $metadata, $componentContext): callable
    {
        return function ($return): void {
            $component = $this->component;

            if (! in_array(NoerdDetail::class, class_uses_recursive($component), true)) {
                return;
            }

            if (! $component->quickCreate || ! $component->modelId) {
                return;
            }

            $component->quickCreate = false;

            if (! empty($component->pageLayout)) {
                $component->pageLayout['quickCreate'] = false;
            }

            $component->dispatch('resizeTopModal');
        };
    }
}
