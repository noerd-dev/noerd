<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Livewire\ComponentHookRegistry;

/**
 * The one registration list of noerd's component hooks. Registered in the
 * provider's register() phase: Livewire wires its ComponentHookRegistry during
 * its own boot(), so a boot()-phase registration could land too late. The
 * order is the order the hooks run in for one lifecycle event.
 */
final class NoerdHooks
{
    /** @var list<class-string<NoerdComponentHook>> */
    public const ALL = [
        QuickCreateExitHook::class,
        RelationFormPersistHook::class,
        LockedPropertiesHook::class,
        ClientWriteGuardHook::class,
        WriteGuardHook::class,
        ServerOnlyMethodsHook::class,
        EmbeddedDetailHostHook::class,
        EmptyNumberFieldHook::class,
        ComponentAccessHook::class,
    ];

    public static function register(): void
    {
        foreach (self::ALL as $hook) {
            ComponentHookRegistry::register($hook);
        }
    }
}
