<?php

declare(strict_types=1);

use Noerd\Services\ThemeRegistry;
use Noerd\Support\ThemeContext;
use Noerd\Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    ThemeContext::clear();
});

/**
 * The size classes a theme declares in its theme.yml, as individual tokens.
 * Read from the definition instead of repeating the literal string, so a theme
 * may retune its buttons without breaking these tests.
 *
 * @return array<int, string>
 */
function zzThemeButtonClasses(string $theme): array
{
    $classes = app(ThemeRegistry::class)->get($theme)->buttonClasses;

    expect($classes)->not->toBeNull("theme {$theme} declares no buttonClasses");

    return preg_split('/\s+/', mb_trim((string) $classes)) ?: [];
}

describe('theme-aware sizing', function (): void {

    it('renders the default size without a theme context', function (): void {
        // The md fallback of the button component itself — no theme involved.
        assertElementHasClasses(
            (string) $this->blade('<x-noerd::button>Save</x-noerd::button>'),
            ['h-8', 'px-4', 'py-1.5', 'text-sm'],
        );
    });

    it('follows the active theme when no explicit size is given', function (): void {
        ThemeContext::set('compact');

        assertElementHasClasses(
            (string) $this->blade('<x-noerd::button>Save</x-noerd::button>'),
            zzThemeButtonClasses('compact'),
        );
    });

    it('lets an explicit size win over the theme context', function (): void {
        ThemeContext::set('compact');

        $html = (string) $this->blade('<x-noerd::button size="lg">Save</x-noerd::button>');

        assertElementHasClasses($html, ['h-10', 'px-5', 'py-2.5', 'text-base']);
        assertNoElementHasClasses($html, zzThemeButtonClasses('compact'));
    });

    it('accepts the theme as an explicit prop', function (): void {
        assertElementHasClasses(
            (string) $this->blade('<x-noerd::button theme="numbered">Save</x-noerd::button>'),
            zzThemeButtonClasses('numbered'),
        );
    });

    it('lets a theme replace the default corner rounding', function (): void {
        ThemeContext::set('numbered');

        // The numbered theme carries its own rounding, so the component default drops out.
        expect(zzThemeButtonClasses('numbered'))->toContain('rounded-none');

        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('rounded-none', false)
            ->assertDontSee('rounded-sm', false);
    });

    it('keeps the default rounding for themes without an own one', function (): void {
        ThemeContext::set('compact');

        expect(zzThemeButtonClasses('compact'))->not->toContain('rounded-none');

        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('rounded-sm', false);
    });

    it('keeps icon-only variants at their fixed square size under a theme', function (): void {
        ThemeContext::set('compact');

        assertElementHasClasses(
            (string) $this->blade('<x-noerd::button variant="control" icon="view-columns" type="button" />'),
            ['h-8', 'w-8'],
        );
    });

    it('renders a default theme without buttonClasses byte-identically', function (): void {
        $withoutContext = (string) $this->blade('<x-noerd::button>Save</x-noerd::button>');

        ThemeContext::set('default');
        $withContext = (string) $this->blade('<x-noerd::button>Save</x-noerd::button>');

        expect($withContext)->toBe($withoutContext);
    });
});

/**
 * The `control` variant exists so header actions match the modal panel chrome
 * (close/fullscreen): a bordered 32px square holding a 16px icon.
 */
describe('control variant', function (): void {

    it('renders a bordered square icon button', function (): void {
        $html = (string) $this->blade('<x-noerd::button variant="control" icon="view-columns" type="button" />');

        assertElementHasClasses($html, ['border', 'border-gray-300', 'h-8', 'w-8']);
        assertElementHasClasses($html, ['w-4', 'h-4']);
    });

    it('leaves the borderless icon variant untouched', function (): void {
        $html = (string) $this->blade('<x-noerd::button variant="icon" icon="view-columns" type="button" />');

        assertElementHasClasses($html, ['h-8', 'w-8']);
        assertElementHasClasses($html, ['w-5', 'h-5']);
        assertNoElementHasClasses($html, ['border', 'border-gray-300']);
    });
});

describe('forms shims', function (): void {

    it('still renders x-noerd::forms.input through the default theme template', function (): void {
        $this->withViewErrors([])
            ->blade('<x-noerd::forms.input name="detailData.name" label="Name" />')
            ->assertSee('name="detailData.name"', false)
            ->assertSee('<input', false);
    });
});
