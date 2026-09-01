<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use Noerd\Support\ThemeContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdPage;

uses(TestCase::class);

beforeEach(function (): void {
    ThemeContext::clear();
});

afterEach(function (): void {
    ThemeContext::clear();
});

/**
 * Test-only page component with a synthetic layout: proves the theme context
 * mechanics without asserting any shipped YAML configuration.
 */
class ThemeContextFixturePage extends Component
{
    use NoerdPage;

    public string $layoutTheme = 'default';

    public function mount(string $layoutTheme = 'default'): void
    {
        $this->layoutTheme = $layoutTheme;
        $this->pageLayout = ['theme' => $layoutTheme, 'fields' => []];
    }

    public function render(): string
    {
        return '<div>theme-during-render:{{ \Noerd\Support\ThemeContext::current() }}</div>';
    }

    protected function componentName(): string
    {
        return 'zz-theme-context-fixture';
    }
}

describe('Theme context lifecycle', function (): void {

    it('exposes the layout theme while the component renders', function (): void {
        Livewire::test(ThemeContextFixturePage::class, ['layoutTheme' => 'numbered'])
            ->assertSee('theme-during-render:numbered');
    });

    it('restores the previous theme after the component rendered', function (): void {
        Livewire::test(ThemeContextFixturePage::class, ['layoutTheme' => 'numbered']);

        expect(ThemeContext::current())->toBeNull();
    });

    it('hands the context back to a hosting page when nested', function (): void {
        ThemeContext::set('compact');

        Livewire::test(ThemeContextFixturePage::class, ['layoutTheme' => 'numbered']);

        expect(ThemeContext::current())->toBe('compact');
    });

    it('leaves chrome rendered after the page on the default button size', function (): void {
        Livewire::test(ThemeContextFixturePage::class, ['layoutTheme' => 'numbered']);

        $this->blade('<x-noerd::button>Save</x-noerd::button>')
            ->assertSee('h-8 px-4 py-1.5 text-sm', false)
            ->assertDontSee('rounded-none', false);
    });
});
