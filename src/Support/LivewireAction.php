<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\Features\SupportEvents\SupportEvents;
use Throwable;

/**
 * The method a Livewire call actually runs. A dispatched event arrives in the
 * `call` hooks as '__dispatch' with the event name in the params, and
 * SupportEvents runs the listener inside the same trigger — hooks keyed on a
 * method name (store, delete) must map the event to its listener method first.
 */
final class LivewireAction
{
    /**
     * @param  array<int, mixed>  $params
     */
    public static function effectiveMethod(object $component, string $method, array $params): string
    {
        if ($method !== '__dispatch') {
            return $method;
        }

        try {
            return SupportEvents::getListenerMethodName($component, (string) ($params[0] ?? ''));
        } catch (Throwable) {
            return $method;
        }
    }

    /**
     * Whether the call is a dispatched event. Its listener runs inside the
     * `call` trigger, so a hook cannot skip it with $returnEarly() — it has to throw.
     */
    public static function isDispatch(string $method): bool
    {
        return $method === '__dispatch';
    }
}
