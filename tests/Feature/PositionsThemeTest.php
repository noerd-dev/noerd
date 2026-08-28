<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Position tables follow the theme', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('renders the default theme unchanged', function (): void {
        $component = Livewire::test('noerd::positions-theme-test', ['theme' => 'default'])
            ->assertSuccessful()
            ->assertSeeHtml('py-8')
            ->assertSeeHtml('h-10')
            ->assertDontSeeHtml('bg-zinc-100')
            ->assertDontSeeHtml('tabular-nums');

        assertElementHasClasses($component->html(), ['table', 'table-sm', 'w-full']);
    });

    it('shrinks controls and padding in the compact theme', function (): void {
        Livewire::test('noerd::positions-theme-test', ['theme' => 'compact'])
            ->assertSuccessful()
            ->assertSeeHtml('h-7')
            ->assertSeeHtml('rounded-sm')
            ->assertSeeHtml('py-3')
            ->assertDontSeeHtml('h-10')
            ->assertDontSeeHtml('tabular-nums');
    });

    it('bands the rows and numbers them in the numbered theme', function (): void {
        $component = Livewire::test('noerd::positions-theme-test', ['theme' => 'numbered'])
            ->assertSuccessful()
            ->assertSeeHtml('bg-zinc-100')
            ->assertSeeHtml('tabular-nums')
            ->assertSeeHtml('h-9')
            ->assertSeeHtml('rounded-none');

        assertElementHasClasses($component->html(), ['border-separate', 'border-spacing-y-1']);

        $html = $component->html();
        $numberCell = fn(int $number): string => '/tabular-nums[^"]*">' . $number . '<\/td>/';

        expect(preg_match($numberCell(1), $html))->toBe(1)
            ->and(preg_match($numberCell(2), $html))->toBe(1)
            ->and(mb_substr_count($html, '>#</th>'))->toBe(1);
    });

    it('renders no number column outside a numbering theme', function (): void {
        Livewire::test('noerd::positions-theme-test', ['theme' => 'compact'])
            ->assertDontSeeHtml('>#</th>');
    });

    it('widens the details row by the number column only when rows are numbered', function (): void {
        Livewire::test('noerd::positions-theme-test', ['theme' => 'default'])
            ->assertSeeHtml('colspan="3"');

        Livewire::test('noerd::positions-theme-test', ['theme' => 'numbered'])
            ->assertSeeHtml('colspan="4"');
    });

    it('renders both accepted tax shapes identically', function (): void {
        // The Livewire wrapper carries the component payload, which of course differs
        // between the two shapes — compare the rendered totals only, anchored on
        // stable text rather than a class attribute whose order may be re-sorted.
        $renderedBlock = function (array $taxes): string {
            $html = Livewire::test('noerd::positions-theme-test', [
                'theme' => 'default',
                'taxes' => $taxes,
            ])->html();

            $anchor = mb_strpos($html, __('Total Net'));
            expect($anchor)->not->toBeFalse();

            return mb_substr($html, $anchor);
        };

        expect($renderedBlock([['tax_rate' => 19, 'tax_total' => 4.2]]))
            ->toBe($renderedBlock(['19' => 4.2]))
            ->toContain('4,20');
    });
});
