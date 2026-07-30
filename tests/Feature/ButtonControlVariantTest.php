<?php

declare(strict_types=1);

use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * The `control` variant exists so header actions match the modal panel chrome
 * (close/fullscreen): a bordered 32px square holding a 16px icon.
 */
it('renders a bordered square icon button', function (): void {
    $this->blade('<x-noerd::button variant="control" icon="view-columns" type="button" />')
        ->assertSee('border border-gray-300', false)
        ->assertSee('h-8 w-8', false);
});

it('sizes the icon like the panel controls', function (): void {
    $this->blade('<x-noerd::button variant="control" icon="view-columns" type="button" />')
        ->assertSee('w-4 h-4', false);
});

it('leaves the borderless icon variant untouched', function (): void {
    $rendered = $this->blade('<x-noerd::button variant="icon" icon="view-columns" type="button" />');

    $rendered->assertSee('h-8 w-8', false)
        ->assertSee('w-5 h-5', false)
        ->assertDontSee('border border-gray-300', false);
});
