<?php

declare(strict_types=1);

use Noerd\Support\ThemeContext;
use Noerd\Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    ThemeContext::clear();
});

describe('Theme-aware buttons', function (): void {

    it('renders the default size without a theme context', function (): void {
        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('h-8 px-4 py-1.5 text-sm', false);
    });

    it('follows the active theme when no explicit size is given', function (): void {
        ThemeContext::set('compact');

        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('h-7 px-2.5 py-1 text-xs', false);
    });

    it('lets an explicit size win over the theme context', function (): void {
        ThemeContext::set('compact');

        $this->blade('<x-noerd::button size="lg">Save</x-noerd::button>')
            ->assertSee('h-10 px-5 py-2.5 text-base', false)
            ->assertDontSee('h-7 px-2.5 py-1 text-xs', false);
    });

    it('accepts the theme as an explicit prop', function (): void {
        $this->blade('<x-noerd::button theme="numbered">Save</x-noerd::button>')
            ->assertSee('h-9 px-4 py-1.5 text-sm', false);
    });

    it('lets a theme replace the default corner rounding', function (): void {
        ThemeContext::set('numbered');

        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('rounded-none', false)
            ->assertDontSee('rounded-sm', false);
    });

    it('keeps the default rounding for themes without an own one', function (): void {
        ThemeContext::set('compact');

        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('rounded-sm', false);
    });

    it('keeps icon-only variants at their fixed square size under a theme', function (): void {
        ThemeContext::set('compact');

        $this->blade('<x-noerd::button variant="control" icon="view-columns" type="button" />')
            ->assertSee('h-8 w-8', false);
    });

    it('renders a default theme without buttonClasses byte-identically', function (): void {
        $withoutContext = (string) $this->blade('<x-noerd::button>Save</x-noerd::button>');

        ThemeContext::set('default');
        $withContext = (string) $this->blade('<x-noerd::button>Save</x-noerd::button>');

        expect($withContext)->toBe($withoutContext);
    });
});

describe('Forms shims', function (): void {

    it('still renders x-noerd::forms.input through the default theme template', function (): void {
        $this->withViewErrors([])
            ->blade('<x-noerd::forms.input name="detailData.name" label="Name" />')
            ->assertSee('name="detailData.name"', false)
            ->assertSee('<input', false);
    });
});
