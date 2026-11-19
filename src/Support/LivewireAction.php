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
 *
 * @internal
 */
final class LivewireAction
{
    /** NoerdDetail's `storeDetail-{name}` listener, a save for every hook. */
    public const HOST_STORE_LISTENER = 'storeFromHost';

    /**
     * The actions every hook treats as a save: guarded by the WriteGuardHook,
     * followed by the relation-form persistence and reported to the page.
     */
    public const SAVE_ACTIONS = ['store', 'save', 'update'];

    /**
     * Whether the effective method (see effectiveMethod()) is a save.
     */
    public static function isSave(string $effectiveMethod): bool
    {
        return in_array($effectiveMethod, self::SAVE_ACTIONS, true);
    }

    /**
     * @param  array<int, mixed>  $params
     */
    public static function effectiveMethod(object $component, string $method, array $params): string
    {
        if ($method === '__dispatch') {
            try {
                $method = SupportEvents::getListenerMethodName($component, (string) ($params[0] ?? ''));
            } catch (Throwable) {
                return $method;
            }
        }

        // The hosting page's save listener runs store() through its host check —
        // dispatched or called directly, it is a save for every hook.
        return $method === self::HOST_STORE_LISTENER ? 'store' : $method;
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
