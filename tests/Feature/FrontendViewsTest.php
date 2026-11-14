<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupLanguage;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The markup the frontend scripts rely on: keyboard reachability, the values
 | the Alpine components read and the numbers a reader sees. Synthetic lists and
 | layouts only — never a shipped configuration.
 */

function zzFrontendReader(string $formatLocale = 'de-DE'): NoerdUser
{
    $user = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $user->setting->update(['format_locale' => $formatLocale]);
    test()->actingAs($user);

    return $user;
}

describe('list keyboard navigation', function (): void {
    it('stores the clicked row as a number and hands the row count to the script', function (): void {
        zzFrontendReader();
        NoerdUser::factory()->count(2)->create();

        $html = Livewire::test(ZzFrontendListComponent::class)->html();

        // A quoted index turned the next arrow key into string concatenation
        // ("0" + 1 = "01").
        expect($html)->toContain('@click="selectedRow = 0"')
            ->and($html)->toContain('@click="selectedRow = 2"')
            ->and($html)->not->toContain("selectedRow = '")
            ->and($html)->toContain('data-row-count="3"');
    });

    it('labels the selection checkboxes', function (): void {
        zzFrontendReader('en-US');

        $html = Livewire::test(ZzFrontendListComponent::class)->html();

        expect($html)->toContain('aria-label="Select all rows on this page"')
            ->and($html)->toContain('aria-label="Select row 1"');
    });
});

describe('numbers in the list chrome', function (): void {
    it('writes the pagination counters in the reader notation', function (): void {
        zzFrontendReader('de-DE');

        $paginator = new LengthAwarePaginator(range(1, 50), 12345, 50, 21);

        $html = view('noerd::components.table.list-pagination-nav', ['paginator' => $paginator])->render();

        expect($html)->toContain('1.001-1.050')
            ->and($html)->toContain('12.345');
    });

    it('writes the summary row like the cells above it', function (): void {
        zzFrontendReader('de-DE');

        $html = Livewire::test(ZzFrontendListComponent::class, ['summary' => ['name' => 1234.5]])->html();

        expect($html)->toContain('1.234,5')
            ->and($html)->not->toContain('>1234.5<');
    });
});

describe('menus and filters', function (): void {
    it('builds the row action menu on the shared action menu', function (): void {
        zzFrontendReader('en-US');

        $html = view('noerd::components.table.table-cell', [
            'row' => 0,
            'column' => 0,
            'label' => '',
            'value' => '',
            'readOnly' => true,
            'id' => 7,
            'columnValue' => 'action',
            'type' => 'text',
            'action' => 'tableAction',
            'actions' => [['label' => 'Archive', 'action' => 'archive']],
            'columnConfig' => [],
            'rowData' => [],
        ])->render();

        expect($html)->toContain('data-noerd-action-menu')
            ->and($html)->toContain('x-data="noerdActionMenu"')
            ->and($html)->toContain('focus-within:opacity-100')
            ->and($html)->toContain('role="menuitem"')
            ->and($html)->toContain('x-on:click="open = false"')
            ->and($html)->toContain('wire:click.prevent="archive(')
            ->and($html)->not->toContain('showDropdown')
            ->and($html)->not->toContain('<title>');
    });

    it('opens the date picker of a date filter by click, not only on hover', function (): void {
        $html = Blade::render(
            '<x-noerd::filters.date-dropdown :filter="$filter" value="" />',
            ['filter' => ['column' => 'created_at', 'label' => 'Created', 'options' => ['today' => 'Today']]],
        );

        expect($html)->toContain('x-on:click="showDatePicker = ! showDatePicker')
            ->and($html)->toContain('@keydown.arrow-right.prevent')
            ->and($html)->toContain('x-ref="dateInput"')
            // Escape is consumed only while the picker or the menu is open; a
            // closed dropdown leaves it to the modal stack.
            ->and($html)->toContain('@keydown.escape="if (showDatePicker) { $event.preventDefault(); $event.stopPropagation();')
            ->and($html)->toContain('else if (open) { $event.preventDefault(); $event.stopPropagation();')
            ->and($html)->not->toContain('@keydown.escape.stop');
    });
});

describe('form fields', function (): void {
    it('gives the translatable textarea the id its label points at', function (string $theme): void {
        zzFrontendReader('en-US');

        Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => [['name' => 'model.notes', 'label' => 'Notes', 'type' => 'translatableTextarea', 'colspan' => 12]],
        ])
            ->assertSeeHtml('id="model.notes"');
    })->with(['default', 'compact', 'numbered']);

    it('marks a rejected currency input through aria-invalid styling', function (string $theme): void {
        zzFrontendReader('en-US');

        Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => [['name' => 'model.amount', 'label' => 'Amount', 'type' => 'currency', 'colspan' => 6]],
        ])
            ->assertSeeHtml('aria-invalid:border-red-500');
    })->with(['default', 'compact', 'numbered']);

    it('shows one honest error text in the dropzone', function (): void {
        zzFrontendReader('en-US');

        Livewire::test('noerd::dropzone')
            ->assertSeeHtml('data-upload-failed="The upload failed. Please try again, or select fewer or smaller files."')
            ->assertDontSeeHtml('data-upload-refused');
    });
});

describe('tabs and switchers', function (): void {
    it('renders a component tab without a URL as a button', function (): void {
        $html = Blade::render('<x-noerd::tab component="zz::thing">Thing</x-noerd::tab>');

        expect($html)->toContain('<button')
            ->and($html)->toContain('type="button"')
            ->and($html)->not->toContain('<a')
            ->and($html)->toContain('focus-visible:ring-2');
    });

    it('renders an inline tab with a visible keyboard focus', function (): void {
        $html = Blade::render('<x-noerd::tab :tabNumber="1">One</x-noerd::tab>');

        expect($html)->toContain('focus-visible:ring-2')
            ->and($html)->not->toContain('focus-visible:outline-none');
    });

    it('switches the setup language with real buttons', function (): void {
        $user = zzFrontendReader('en-US');

        foreach ([['de', 'Deutsch', true, 1], ['en', 'English', false, 2]] as [$code, $name, $isDefault, $sort]) {
            SetupLanguage::query()->updateOrCreate(
                ['tenant_id' => $user->selected_tenant_id, 'code' => $code],
                ['name' => $name, 'is_active' => true, 'is_default' => $isDefault, 'sort_order' => $sort],
            );
        }

        Livewire::test('noerd::setup-language-switcher')
            ->assertSeeHtml('type="button"')
            ->assertSeeHtml('aria-label="English"')
            ->assertDontSeeHtml('<a ');
    });

    it('renders the top bar without nested controls or English tooltips', function (): void {
        zzFrontendReader('de-DE');
        app()->setLocale('de');

        $html = Livewire::test('noerd::layout.top-bar')->html();

        expect($html)->not->toContain('<title>')
            ->and($html)->not->toMatch('/<a\\b[^>]*>\\s*<button/')
            ->and($html)->toContain('aria-label="Setup anzeigen"')
            ->and($html)->toContain('Benutzermenü öffnen')
            ->and($html)->not->toContain('Open user menu');
    });

    it('keeps the tenant switcher reachable below the lg breakpoint', function (): void {
        zzFrontendReader('en-US');

        Livewire::test('noerd::layout.tenant-switcher')
            ->assertDontSeeHtml('hidden lg:flex')
            ->assertSeeHtml('max-lg:sr-only');
    });
});

class ZzFrontendListComponent extends Component
{
    use NoerdList;

    /** @var array<string, mixed>|null */
    public ?array $summary = null;

    /**
     * @return array<string, mixed>
     */
    public function listData(): array
    {
        return $this->buildList(
            $this->listQuery(NoerdUser::class)->orderBy('id')->paginate($this->perPage),
            'zz-frontend-list',
        );
    }

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list :summary="$summary" /></x-noerd::page>';
    }

    protected function componentName(): string
    {
        return 'zz-frontend-list';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Users',
            'multiSelect' => true,
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
        ];
    }
}
