<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Compact detail layout', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('renders successfully in compact mode', function (): void {
        Livewire::test('noerd::compact-field-test', [
            'initialModel' => [],
            'compact' => true,
        ])->assertSuccessful();
    });

    it('emits the compact grid marker when compact is true', function (): void {
        Livewire::test('noerd::compact-field-test', [
            'initialModel' => [],
            'compact' => true,
        ])->assertSeeHtml('data-compact="true"');
    });

    it('applies the horizontal label wrapper classes when compact', function (): void {
        Livewire::test('noerd::compact-field-test', [
            'initialModel' => [],
            'compact' => true,
        ])
            ->assertSeeHtml('flex items-center gap-2')
            ->assertSeeHtml('flex items-start gap-2')
            ->assertSeeHtml('!pb-0 w-36 shrink-0 truncate');
    });

    it('does not emit compact markers by default', function (): void {
        Livewire::test('noerd::compact-field-test', [
            'initialModel' => [],
            'compact' => false,
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('data-compact="true"')
            ->assertDontSeeHtml('w-36 shrink-0');
    });

    it('still renders field labels in compact mode', function (): void {
        Livewire::test('noerd::compact-field-test', [
            'initialModel' => [],
            'compact' => true,
        ])
            ->assertSeeHtml('for="model.title"')
            ->assertSeeHtml('for="model.notes"');
    });
});
