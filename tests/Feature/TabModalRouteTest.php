<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<int, array<string, mixed>>  $tabs
 */
function renderLayoutTabs(array $tabs, mixed $modelId = null): string
{
    return html_entity_decode(
        Blade::render(
            '<x-noerd::tabs :layout="$layout" :modelId="$modelId" />',
            ['layout' => ['tabs' => $tabs], 'modelId' => $modelId],
        ),
    );
}

beforeEach(function (): void {
    registerTestLivewireRoute('zz-tab-record/{modelId}', 'noerd::theme-test', 'zz.tab.record');
    registerTestLivewireRoute('zz-tab-page', 'noerd::theme-test', 'zz.tab.page');
});

it('renders a modalRoute tab with a full-page href and a $modalRoute click', function (): void {
    $html = renderLayoutTabs([
        [
            'label' => 'Related Record',
            'modalRoute' => 'zz.tab.record',
            'arguments' => ['modelId' => '$modelId'],
        ],
    ], 42);

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.tab.record')
        ->toContain('zz-tab-record/42')
        ->not->toContain('$modal(');
});

it('falls back to the component tab when the modalRoute is not registered', function (): void {
    $html = renderLayoutTabs([
        [
            'label' => 'Related Record',
            'modalRoute' => 'zz.tab.route.that.does.not.exist',
            'component' => 'zz::record-detail',
        ],
    ], 5);

    expect($html)->toContain('$modal(')
        ->toContain('zz::record-detail')
        ->not->toContain('$modalRoute(');
});

it('keeps route-only tabs as plain navigate tabs', function (): void {
    $html = renderLayoutTabs([
        ['label' => 'Overview', 'route' => 'zz.tab.page'],
    ]);

    expect($html)->toContain('wire:navigate')
        ->not->toContain('$modalRoute(')
        ->not->toContain('$modal(');
});

it('keeps numbered tabs unchanged', function (): void {
    $html = renderLayoutTabs([
        ['label' => 'General', 'number' => 1],
    ]);

    expect($html)->toContain('currentTab= 1')
        ->not->toContain('$modalRoute(');
});

it('opens a component tab as a modal with the record id resolved', function (): void {
    $html = renderLayoutTabs([
        [
            'label' => 'Related Records',
            'component' => 'zz::records-list',
            'arguments' => ['recordType' => 'Zz\\Models\\Record', 'modelId' => '$modelId'],
        ],
    ], 42);

    expect($html)->toContain('$modal(')
        ->toContain('zz::records-list')
        ->not->toContain('$modalRoute(');

    // The arguments reach Alpine as a JSON literal: '$modelId' is resolved to the
    // record id, static values (a morph class here) pass through untouched.
    preg_match("/JSON\\.parse\\('(.*?)'\\)/", $html, $matches);

    // Unwrap twice: the JS string literal, then the JSON it carries.
    $json = json_decode('"' . ($matches[1] ?? '') . '"');

    expect(json_decode((string) $json, true))
        ->toBe(['recordType' => 'Zz\\Models\\Record', 'modelId' => 42]);
});

it('hides a requiresId tab until the record is saved', function (): void {
    $tab = [
        'label' => 'Related Records',
        'component' => 'zz::records-list',
        'requiresId' => true,
    ];

    expect(renderLayoutTabs([$tab]))->not->toContain('zz::records-list')
        ->and(renderLayoutTabs([$tab], 42))->toContain('zz::records-list');
});

it('hides a tab whose viewExists view is not registered', function (): void {
    $html = renderLayoutTabs([
        [
            'label' => 'Related Records',
            'component' => 'zz::records-list',
            'viewExists' => 'zz::components.records-list',
        ],
    ], 42);

    expect($html)->not->toContain('zz::records-list');

    $registered = renderLayoutTabs([
        [
            'label' => 'Theme Test',
            'component' => 'noerd::theme-test',
            'viewExists' => 'noerd::components.theme-test',
        ],
    ], 42);

    expect($registered)->toContain('noerd::theme-test');
});
