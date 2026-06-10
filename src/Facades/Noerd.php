<?php

namespace Noerd\Facades;

use Illuminate\Support\Facades\Facade;
use Noerd\Services\NoerdManager;

/**
 * @method static void modal(string $component, mixed $arguments = [], ?string $position = null, ?string $size = null, bool $quickCreate = false)
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
