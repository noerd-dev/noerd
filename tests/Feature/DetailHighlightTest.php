<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

it('rings the fields marked highlight and leaves the others plain', function (): void {
    $html = Livewire::test('noerd-test::highlight-test')->assertSuccessful()->html();

    // Two of the three fields are marked — the nested block is walked too.
    expect(mb_substr_count($html, 'ring-amber-400/70'))->toBe(2);
});

it('marks the label of a highlighted field', function (): void {
    Livewire::test('noerd-test::highlight-test')
        ->assertSee(__('This value was proposed for you. Check it before saving.'));
});

it('shows what the field held before, only where a previous value was recorded', function (): void {
    Livewire::test('noerd-test::highlight-test')
        ->assertDontSee(__('was: :value', ['value' => 'the old value']));

    Livewire::test('noerd-test::highlight-test', ['withPrevious' => true])
        ->assertSee(__('was: :value', ['value' => 'the old value']));
});

it('rings the field in every theme, not just the default one', function (): void {
    foreach (['default', 'compact', 'numbered'] as $theme) {
        $html = Livewire::test('noerd-test::highlight-test', ['theme' => $theme])->html();

        expect(mb_substr_count($html, 'ring-amber-400/70'))->toBe(2, "theme {$theme}");
    }
});
