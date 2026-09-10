<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A `type: number` field passes its `step` through to the input in every
 * built-in theme, so a YAML may allow decimals (a VAT rate of 5.5) — proven
 * with a synthetic layout, never with a shipped YAML.
 */
beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

it('renders the step attribute of a number field', function (string $theme): void {
    Livewire::test('noerd-test::theme-test', [
        'initialModel' => ['rate' => 19],
        'theme' => $theme,
        'fields' => [
            ['name' => 'model.rate', 'label' => 'Rate', 'type' => 'number', 'step' => '0.01', 'colspan' => 6],
        ],
    ])
        ->assertSuccessful()
        ->assertSeeHtml('type="number"')
        ->assertSeeHtml('step="0.01"');
})->with(['default', 'compact', 'numbered']);

it('renders no step attribute without the option and never on a text field', function (): void {
    Livewire::test('noerd-test::theme-test', [
        'initialModel' => [],
        'theme' => 'default',
        'fields' => [
            ['name' => 'model.rate', 'label' => 'Rate', 'type' => 'number', 'colspan' => 6],
            ['name' => 'model.code', 'label' => 'Code', 'type' => 'text', 'step' => '0.01', 'colspan' => 6],
        ],
    ])
        ->assertSuccessful()
        ->assertDontSeeHtml('step=');
});
