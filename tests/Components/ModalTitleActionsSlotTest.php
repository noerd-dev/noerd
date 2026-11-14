<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

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

it('binds the title text to the id the modal panel labels its dialog with', function (): void {
    $html = Blade::render('<x-noerd::modal-title>Dialog Title</x-noerd::modal-title>');

    // noerd/modal's panel offers `modalTitleId` and points aria-labelledby at it;
    // the binding names the title text only, never the action buttons.
    expect($html)->toMatch('/<span\s+class="contents"\s+:id="\(typeof isModal !== \'undefined\' && isModal && typeof modalTitleId !== \'undefined\'\) \? modalTitleId : null"\s*>\s*Dialog Title\s*<\/span>/');
});
