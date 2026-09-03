<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Noerd\Traits\NoerdPage;

/**
 * Makes the quick-create exit a framework default for every detail component,
 * with no per-component wiring.
 *
 * Quick-create is a narrow modal for NEW records only (see the NoerdDetail trait).
 * Once such a record is saved and gets its id, the modal must leave quick-create
 * mode so the full detail renders and the panel widens.
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
final class QuickCreateExitHook extends ComponentHook
{
    public function call($method, $params, $returnEarly, $metadata, $componentContext): callable
    {
        return function ($return): void {
            $component = $this->component;

            if (! in_array(NoerdPage::class, class_uses_recursive($component), true)) {
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
