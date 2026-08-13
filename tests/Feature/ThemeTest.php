<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Theme system', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('emits no theme markers in the default theme', function (): void {
        Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'default',
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('data-theme=')
            ->assertDontSeeHtml('data-compact')
            ->assertDontSeeHtml('bg-zinc-100');
    });

    it('emits the theme marker for the compact theme', function (): void {
        $component = Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'compact',
        ])
            ->assertSeeHtml('data-theme="compact"')
            ->assertDontSeeHtml('data-compact');

        assertElementHasClasses($component->html(), ['w-36', 'shrink-0', 'truncate']);
    });

    it('renders the numbered theme with full-width gray rows and number cells', function (): void {
        $component = Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'numbered',
        ])
            ->assertSeeHtml('data-theme="numbered"')
            ->assertSeeHtml('col-span-full')
            ->assertSeeHtml('bg-zinc-100')
            ->assertSeeHtml('tabular-nums')
            ->assertSeeHtml('gap-y-1');

        assertElementHasClasses($component->html(), ['text-right', 'truncate']);
    });

    it('numbers rows automatically, skips spacers and lets an explicit number win', function (): void {
        $component = Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'numbered',
        ]);

        // Fields 1 + 2 get automatic numbers; the spacer after them consumes NO number;
        // the currency field pins number 21 in the layout (still consuming slot 3), so the
        // textarea after it gets number 4. Match the number cell content whitespace-
        // insensitively so Blade reformatting of numbered-row.blade.php cannot break this.
        $html = $component->html();
        $numberCell = fn(int $number): string => '/tabular-nums[^"]*">\s*' . $number . '\s*<\/div>/';

        expect(preg_match($numberCell(1), $html))->toBe(1)
            ->and(preg_match($numberCell(2), $html))->toBe(1)
            ->and(preg_match($numberCell(21), $html))->toBe(1)
            ->and(preg_match($numberCell(4), $html))->toBe(1)
            ->and(preg_match($numberCell(3), $html))->toBe(0)
            ->and(preg_match($numberCell(5), $html))->toBe(0);
    });

    it('falls back to the default theme for unknown theme names', function (): void {
        Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'bogus',
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('data-theme=')
            ->assertDontSeeHtml('bg-zinc-100');
    });

    it('honors a per-field theme override', function (): void {
        // model.plain declares theme 'default' — in the numbered theme it renders as a
        // standard label-on-top input while its siblings get the row chrome.
        $component = Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'numbered',
        ])
            ->assertSeeHtml('for="model.plain"');

        assertElementHasClasses($component->html(), ['col-span-1', 'sm:col-span-6']);
    });

    it('inherits the theme in nested blocks', function (): void {
        Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'numbered',
        ])
            ->assertSeeHtml('for="model.nested"')
            // The nested block renders its own numbered grid marker.
            ->assertSeeHtml('data-theme="numbered"');
    });

    it('still renders field labels in the numbered theme', function (): void {
        Livewire::test('noerd::theme-test', [
            'initialModel' => [],
            'theme' => 'numbered',
        ])
            ->assertSeeHtml('for="model.title"')
            ->assertSeeHtml('for="model.notes"');
    });
});
