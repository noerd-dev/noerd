<?php

declare(strict_types=1);

namespace Noerd\Facades;

use Illuminate\Support\Facades\Facade;
use Noerd\Services\NoerdManager;

/**
 * @method static void modal(string $component, mixed $arguments = [], ?string $position = null, ?string $size = null, bool $quickCreate = false)
 * @method static void modalRoute(string $routeName, mixed $arguments = [], ?string $position = null, ?string $size = null, ?string $fallbackComponent = null, bool $rewriteUrl = true)
 * @method static void modalFor(?string $routeName, ?string $component, mixed $arguments = [], ?string $position = null, ?string $size = null, bool $rewriteUrl = true)
 *
 * @see \Noerd\Services\NoerdManager
 */
class Noerd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NoerdManager::class;
    }
}
