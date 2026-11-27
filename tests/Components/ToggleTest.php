<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('renders a switch bound like a checkbox with its label and hint', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-noerd::toggle wire:model.live="settings.enabled" label="Zz label" description="Zz hint" id="zz-toggle" />
    BLADE);

    expect($html)
        ->toContain('type="checkbox"')
        ->toContain('role="switch"')
        ->toContain('wire:model.live="settings.enabled"')
        ->toContain('for="zz-toggle"')
        ->toContain('Zz label')
        ->toContain('Zz hint')
        ->not->toMatch('/<input[^>]*\sdisabled[\s>]/');
});

it('disables the switch', function (): void {
    $html = Blade::render('<x-noerd::toggle wire:model="zz" label="Zz" :disabled="true" />');

    expect($html)->toMatch('/<input[^>]*\sdisabled[\s>]/')->toContain('cursor-not-allowed');
});

it('renders the switches of a toggle group as padded rows of one card', function (): void {
    $grouped = Blade::render(<<<'BLADE'
        <x-noerd::toggle-group title="Zz group">
            <x-noerd::toggle wire:model="zz.a" label="Zz A" />
            <x-noerd::toggle wire:model="zz.b" label="Zz B">
                <x-slot:below><span>zz-below</span></x-slot:below>
            </x-noerd::toggle>
        </x-noerd::toggle-group>
    BLADE);
    $standalone = Blade::render('<x-noerd::toggle wire:model="zz" label="Zz" />');

    expect($grouped)
        ->toContain('Zz group')
        ->toContain('divide-y')
        ->toContain('zz-below')
        ->toContain('*:px-4 *:py-4')
        ->and(mb_substr_count($grouped, 'role="switch"'))->toBe(2)
        ->and($standalone)->not->toContain('*:px-4');
});
