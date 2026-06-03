<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

uses(Tests\TestCase::class);

it('renders the actions slot with modal-aware spacing in the header', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-noerd::modal-title>
            My Title
            <x-slot:actions><a href="#">Zur Seite</a></x-slot:actions>
        </x-noerd::modal-title>
    BLADE);

    expect($html)
        ->toContain('My Title')
        ->toContain('Zur Seite')
        ->toContain("isModal ? modalControlsClass : ''");
});

it('omits the actions wrapper when no actions slot is passed', function (): void {
    $html = Blade::render('<x-noerd::modal-title>Just A Title</x-noerd::modal-title>');

    expect($html)
        ->toContain('Just A Title')
        ->not->toContain("isModal ? modalControlsClass : ''");
});
