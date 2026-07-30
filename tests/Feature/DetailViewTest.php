<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Detail view system', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('emits no view markers in the default view', function (): void {
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'default',
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('data-view=')
            ->assertDontSeeHtml('data-compact="true"')
            ->assertDontSeeHtml('bg-zinc-100');
    });

    it('emits both the canonical and the legacy marker for the compact view', function (): void {
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'compact',
        ])
            ->assertSeeHtml('data-view="compact"')
            ->assertSeeHtml('data-compact="true"')
            ->assertSeeHtml('w-36 shrink-0 truncate');
    });

    it('renders the numbered view with full-width gray rows and number cells', function (): void {
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'numbered',
        ])
            ->assertSeeHtml('data-view="numbered"')
            ->assertDontSeeHtml('data-compact="true"')
            ->assertSeeHtml('col-span-full')
            ->assertSeeHtml('bg-zinc-100')
            ->assertSeeHtml('tabular-nums')
            ->assertSeeHtml('gap-y-1')
            ->assertSeeHtml('text-right truncate');
    });

    it('numbers rows automatically, skips spacers and lets an explicit number win', function (): void {
        $component = Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'numbered',
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

    it('falls back to the default view for unknown view names', function (): void {
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'bogus',
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('data-view=')
            ->assertDontSeeHtml('bg-zinc-100');
    });

    it('honors a per-field view override', function (): void {
        // model.plain declares view 'default' — in numbered mode it renders as a
        // standard label-on-top input while its siblings get the row chrome.
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'numbered',
        ])
            ->assertSeeHtml('for="model.plain"')
            ->assertSeeHtml('col-span-1 sm:col-span-6');
    });

    it('inherits the view in nested blocks', function (): void {
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'numbered',
        ])
            ->assertSeeHtml('for="model.nested"')
            // The nested block renders its own numbered grid marker.
            ->assertSeeHtml('data-view="numbered"');
    });

    it('still renders field labels in the numbered view', function (): void {
        Livewire::test('noerd::detail-view-test', [
            'initialModel' => [],
            'view' => 'numbered',
        ])
            ->assertSeeHtml('for="model.title"')
            ->assertSeeHtml('for="model.notes"');
    });
});
