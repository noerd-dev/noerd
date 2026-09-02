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
