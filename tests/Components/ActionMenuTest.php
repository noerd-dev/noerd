<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('renders a kebab trigger and a role=menu panel by default', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-noerd::action-menu>
            <x-noerd::action-menu-item wire:click="doIt">Do It</x-noerd::action-menu-item>
        </x-noerd::action-menu>
    BLADE);

    expect($html)
        ->toContain('x-data="{ open: false }"')
        ->toContain('role="menu"')
        ->toContain('role="menuitem"')
        ->toContain('Do It')
        ->toContain('wire:click="doIt"')
        // default trigger: kebab button with an accessible label
        ->toContain('Actions')
        ->toContain('right-0 origin-top-right');
});

it('replaces the default trigger with the trigger slot', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-noerd::action-menu>
            <x-slot:trigger>
                <button x-on:click="open = ! open">My Trigger</button>
            </x-slot:trigger>
            <x-noerd::action-menu-item>Entry</x-noerd::action-menu-item>
        </x-noerd::action-menu>
    BLADE);

    expect($html)
        ->toContain('My Trigger')
        ->not->toContain('<span class="sr-only">Actions</span>');
});

it('aligns the panel to the left when asked', function (): void {
    $html = Blade::render('<x-noerd::action-menu align="left">x</x-noerd::action-menu>');

    expect($html)
        ->toContain('left-0 origin-top-left')
        ->not->toContain('right-0 origin-top-right');
});

it('anchors the panel instead of positioning it absolutely', function (): void {
    $html = Blade::render('<x-noerd::action-menu anchor="$refs.btn">x</x-noerd::action-menu>');

    expect($html)
        ->toContain('x-anchor.bottom-end="$refs.btn"')
        ->not->toContain('absolute mt-2');
});

it('renders an item as a link only when a href is given', function (): void {
    $link = Blade::render('<x-noerd::action-menu-item href="/somewhere" navigate>Go</x-noerd::action-menu-item>');
    $button = Blade::render('<x-noerd::action-menu-item>Press</x-noerd::action-menu-item>');

    expect($link)
        ->toContain('<a')
        ->toContain('href="/somewhere"')
        ->toContain('wire:navigate')
        ->and($button)
        ->toContain('<button')
        ->toContain('type="button"')
        ->not->toContain('href=');
});

it('closes the menu when an item is clicked', function (): void {
    $html = Blade::render('<x-noerd::action-menu-item>Entry</x-noerd::action-menu-item>');

    expect($html)->toContain('x-on:click="open = false"');
});

it('marks the active item', function (): void {
    $active = Blade::render('<x-noerd::action-menu-item :active="true">On</x-noerd::action-menu-item>');
    $inactive = Blade::render('<x-noerd::action-menu-item :active="false">Off</x-noerd::action-menu-item>');

    expect($active)->toContain('font-semibold text-gray-900')
        ->and($inactive)->toContain('font-normal text-gray-700');
});
