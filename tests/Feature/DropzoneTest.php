<?php

declare(strict_types=1);

use Livewire\Livewire;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('renders the dropzone with computed accept and max size', function (): void {
    Livewire::test('noerd::dropzone', ['rules' => ['mimes:pdf,jpg', 'max:2048']])
        ->assertStatus(200)
        ->assertSeeHtml('accept=".pdf,.jpg"')
        ->assertSee('2.00 MB');
});

it('renders an uploaded file with a keyed row and an accessible remove button', function (): void {
    Livewire::test('noerd::dropzone', ['rules' => ['mimes:pdf', 'max:2048']])
        ->set('files', [
            ['name' => 'invoice.pdf', 'extension' => 'pdf', 'size' => 2048, 'path' => '/tmp/invoice.pdf', 'mime_type' => 'application/pdf'],
        ])
        ->assertStatus(200)
        ->assertSee('invoice.pdf')
        ->assertSee('2.00 KB')
        ->assertSeeHtml('aria-label="Remove file"')
        ->assertSeeHtml('wire:key="dropzone-file-0-invoice.pdf"');
});

it('keeps the file display helpers off the client-callable surface', function (): void {
    $component = Livewire::test('noerd::dropzone')->instance();

    expect((new ReflectionMethod($component, 'getFileDisplayName'))->isPublic())->toBeFalse()
        ->and((new ReflectionMethod($component, 'getFileSize'))->isPublic())->toBeFalse();
});
