<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\ComponentHook;
use Livewire\Features\SupportEvents\SupportEvents;
use Noerd\Traits\NoerdDetail;
use Throwable;

/**
 * Reusable base for "run after a detail component saved its record" hooks
 * (modelled on QuickCreateExitHook): call() returns a finish callback that
 * fires once the wrapped action has completed — after store() persisted the
 * record and set $modelId, but before the view renders, so mutations of
 * $detailData/$pageLayout still reach the response.
 *
 * The base owns all shared mechanics; subclasses implement only afterSave():
 * - Only save actions trigger it (store/save/update). An event-triggered save
 *   arrives as '__dispatch' with the event name in the params — SupportEvents
 *   runs the listener inside the same trigger, so the finish callback still
 *   fires after it; the event is mapped to its listener method to recognize it.
 * - A thrown ValidationException never reaches the finish callback (the action
 *   runs before it), so nothing is persisted after failed validation.
 * - store() early-returns for write-denied users while finish callbacks still
 *   run — canWriteObject() is therefore re-checked here.
 */
abstract class DetailSaveHook extends ComponentHook
{
    private const SAVE_ACTIONS = ['store', 'save', 'update'];

    abstract protected function afterSave(Component $component, Model $record): void;

    public function call($method, $params, $returnEarly, $metadata, $componentContext): callable
    {
        $effective = $method;
        if ($method === '__dispatch') {
            try {
                $effective = SupportEvents::getListenerMethodName($this->component, $params[0] ?? '');
            } catch (Throwable) {
                $effective = $method;
            }
        }

        return function ($return) use ($effective): void {
            if (! in_array($effective, self::SAVE_ACTIONS, true)) {
                return;
            }

            $component = $this->component;

            if (! in_array(NoerdDetail::class, class_uses_recursive($component), true)) {
                return;
            }

            if (! isset($component->detailModel) || ! $component->modelId) {
                return;
            }

            // A caught ValidationException still reaches the finish callback —
            // nothing was saved, so nothing may be written back.
            if ($component->getErrorBag()->isNotEmpty()) {
                return;
            }

            if (! $component->canWriteObject()) {
                return;
            }

            $modelClass = $component->detailModel;
            $record = $modelClass::find($component->modelId);

            if (! $record instanceof Model) {
                return;
            }

            $this->afterSave($component, $record);
        };
    }
}
