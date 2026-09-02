<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Page disableModal fallback', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    /** The page root breaks out of the surrounding padding via a negative margin style. */
    $breakout = '/<div\b[^>]*\bstyle="[^"]*margin-left[^"]*"/';

    it('does not apply the breakout style by default', function () use ($breakout): void {
        $html = Livewire::test('noerd-test::page-chrome-list')
            ->assertSuccessful()
            ->html();

        expect($html)->not->toMatch($breakout);
    });

    it('applies the breakout style when disableModal is set as a mount property', function () use ($breakout): void {
        $html = Livewire::test('noerd-test::page-chrome-list', ['disableModal' => true])
            ->assertSuccessful()
            ->html();

        expect($html)->toMatch($breakout);
    });
});
