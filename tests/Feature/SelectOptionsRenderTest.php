<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A rendered <select> must show what the component actually holds. Without a
 | matching <option> the browser falls back to displaying the first one, which
 | is how a null value used to read as "the first option is selected" and was
 | then lost on save. The layout comes from a synthetic fixture, never from a
 | shipped config.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

function zzSelectField(array $overrides = []): array
{
    return array_merge([
        'name' => 'model.status',
        'label' => 'Status',
        'type' => 'select',
        'colspan' => 6,
        'options' => [
            ['value' => 'alpha', 'label' => 'Alpha'],
            ['value' => 'beta', 'label' => 'Beta'],
        ],
    ], $overrides);
}

it('renders an empty leading option when the bound value is null', function (): void {
    Livewire::test('noerd::theme-test', [
        'initialModel' => [],
        'fields' => [zzSelectField()],
    ])
        ->assertSeeHtml('<option value=""></option>');
});

it('renders no empty option once the value matches an option', function (): void {
    Livewire::test('noerd::theme-test', [
        'initialModel' => ['status' => 'beta'],
        'fields' => [zzSelectField()],
    ])
        ->assertDontSeeHtml('<option value=""></option>');
});

it('renders the placeholder as the empty option', function (): void {
    Livewire::test('noerd::theme-test', [
        'initialModel' => [],
        'fields' => [zzSelectField(['placeholder' => 'Please choose'])],
    ])
        ->assertSeeHtml('<option value="">Please choose</option>');
});

it('renders a value that matches no option instead of silently showing the first one', function (): void {
    Livewire::test('noerd::theme-test', [
        'initialModel' => ['status' => 'gamma'],
        'fields' => [zzSelectField()],
    ])
        ->assertSeeHtml('<option value="gamma">gamma</option>');
});

it('applies the same option handling in every theme', function (string $theme): void {
    Livewire::test('noerd::theme-test', [
        'initialModel' => [],
        'theme' => $theme,
        'fields' => [zzSelectField()],
    ])
        ->assertSeeHtml('<option value=""></option>');
})->with(['default', 'compact', 'numbered']);
