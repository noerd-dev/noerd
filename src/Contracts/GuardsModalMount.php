<?php

declare(strict_types=1);

namespace Noerd\Contracts;

/**
 * A route middleware that guards ACCESS to a screen and must keep guarding it
 * when the modal stack opens the screen's component.
 *
 * The modal stack mounts a component the browser names (`noerdModal` is a
 * client event) without running the middleware of the component's routes.
 * `Noerd\Support\ComponentAccessGuard::allowsInModal()` re-checks the core's
 * own access middleware (`app-access`, `action-permission`, `setup`,
 * `profile`) and asks every middleware of a route that implements this
 * contract — directly, by alias or inside a middleware group. A module whose
 * routes are protected by an access middleware of its OWN implements it;
 * otherwise its screens can be mounted through the modal stack by any
 * signed-in user.
 *
 * The answer must be the middleware's own rule, evaluated for the current
 * request without running the pipeline: no redirect, no session write, no
 * response — only whether the current user may reach the screen.
 */
interface GuardsModalMount
{
    /**
     * Whether the current user satisfies the middleware.
     *
     * @param  string  ...$parameters  The middleware's route parameters (`name:a,b` → `'a', 'b'`).
     */
    public static function allowsModalMount(string ...$parameters): bool;
}
