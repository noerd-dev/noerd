<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('only wires a click handler when one is given', function (): void {
    expect(Blade::render('<x-noerd::toggle model="closed" click="store" />'))
        ->toContain('wire:click="store"');

    expect(Blade::render('<x-noerd::toggle model="closed" />'))
        ->not->toContain('wire:click');
});
