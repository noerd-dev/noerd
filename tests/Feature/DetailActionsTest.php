<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

if (! function_exists('renderDetailActions')) {
    function renderDetailActions(array $actions, mixed $modelId = null, array $urls = []): string
    {
        return Blade::render(
            '<x-noerd::detail-actions :layout="$layout" :modelId="$modelId" :urls="$urls" />',
            ['layout' => ['actions' => $actions], 'modelId' => $modelId, 'urls' => $urls],
        );
    }
}

it('renders a wire:click button for a method action', function (): void {
    $html = renderDetailActions([
        ['label' => 'Do Thing', 'action' => 'doThing', 'confirm' => 'Sure?'],
    ], 5);

    expect($html)->toContain('wire:click="doThing"')
        ->toContain('wire:confirm="Sure?"')
        ->toContain('Do Thing');
});

it('renders a modalComponent action as an Alpine $modal call with the $modelId token resolved', function (): void {
    $html = renderDetailActions([
        [
            'label' => 'New Order',
            'modalComponent' => 'pos::pos-order-modal',
            'arguments' => ['customerId' => '$modelId'],
        ],
    ], 42);

    expect($html)->toContain('$modal(')
        ->toContain('pos::pos-order-modal')
        ->toContain('customerId')
        ->toContain('42')
        ->not->toContain('wire:click');
});

it('hides an action whose viewExists view is not registered', function (): void {
    $html = renderDetailActions([
        [
            'label' => 'New Order',
            'modalComponent' => 'missing::modal',
            'viewExists' => 'missing::components.modal',
        ],
    ], 5);

    expect($html)->not->toContain('New Order');
});

it('shows an action whose viewExists view is registered', function (): void {
    $html = renderDetailActions([
        [
            'label' => 'Visible Modal Action',
            'modalComponent' => 'some::modal',
            'viewExists' => 'noerd::components.detail-actions',
        ],
    ], 5);

    expect($html)->toContain('Visible Modal Action');
});

it('hides actions until the record is saved unless requiresId is false', function (): void {
    $actions = [
        ['label' => 'Needs Id', 'action' => 'needsId'],
        ['label' => 'Always Visible', 'action' => 'alwaysVisible', 'requiresId' => false],
    ];

    $withoutId = renderDetailActions($actions, null);
    $withId = renderDetailActions($actions, 5);

    expect($withoutId)->not->toContain('Needs Id')
        ->toContain('Always Visible');
    expect($withId)->toContain('Needs Id')
        ->toContain('Always Visible');
});

it('renders a route action as an Alpine $modalRoute call with the $modelId token resolved', function (): void {
    registerTestLivewireRoute('zz-detail-action/{modelId}', 'noerd::theme-test', 'zz.detail.action');

    $html = renderDetailActions([
        [
            'label' => 'Open Record',
            'route' => 'zz.detail.action',
            'arguments' => ['modelId' => '$modelId'],
        ],
    ], 42);

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.detail.action')
        ->toContain('42')
        ->not->toContain('$modal(')
        ->not->toContain('wire:click');
});

it('prefers route over modalComponent on the same action and keeps the component as fallback', function (): void {
    registerTestLivewireRoute('zz-detail-action-both/{modelId}', 'noerd::theme-test', 'zz.detail.both');

    $html = renderDetailActions([
        [
            'label' => 'Open Record',
            'route' => 'zz.detail.both',
            'modalComponent' => 'zz::fallback-detail',
            'arguments' => ['modelId' => '$modelId'],
        ],
    ], 7);

    expect($html)->toContain('$modalRoute(')
        ->toContain('fallbackComponent')
        ->toContain('zz::fallback-detail');
});

it('hides a route action whose route is not registered and has no modalComponent', function (): void {
    $html = renderDetailActions([
        ['label' => 'Ghost Action', 'route' => 'zz.route.that.does.not.exist'],
    ], 5);

    expect($html)->not->toContain('Ghost Action');
});

it('falls back to the component branch for a route action with a modalComponent', function (): void {
    $html = renderDetailActions([
        [
            'label' => 'Fallback Action',
            'route' => 'zz.route.that.does.not.exist',
            'modalComponent' => 'zz::fallback-detail',
        ],
    ], 5);

    expect($html)->toContain('Fallback Action')
        ->toContain('$modal(')
        ->toContain('zz::fallback-detail')
        ->not->toContain('$modalRoute(');
});

it('applies requiresId and viewExists to route actions as well', function (): void {
    registerTestLivewireRoute('zz-detail-action-guard/{modelId}', 'noerd::theme-test', 'zz.detail.guard');

    $needsId = renderDetailActions([
        ['label' => 'Needs Id Route', 'route' => 'zz.detail.guard'],
    ], null);

    $missingView = renderDetailActions([
        ['label' => 'Missing View Route', 'route' => 'zz.detail.guard', 'viewExists' => 'missing::components.modal'],
    ], 5);

    expect($needsId)->not->toContain('Needs Id Route');
    expect($missingView)->not->toContain('Missing View Route');
});

it('swaps the label for the loading text while a method action runs', function (): void {
    $html = renderDetailActions([
        ['label' => 'Resend order confirmation', 'action' => 'sendOrderMail', 'loading' => 'Sending...'],
    ], 5);

    expect($html)->toContain('wire:loading.attr="disabled"')
        ->toContain('wire:target="sendOrderMail"')
        ->toContain('wire:loading.remove')
        ->toContain('Sending...');
});

it('renders a plain label when a method action has no loading text', function (): void {
    $html = renderDetailActions([
        ['label' => 'Do Thing', 'action' => 'doThing'],
    ], 5);

    expect($html)->toContain('Do Thing')
        ->not->toContain('wire:loading.remove');
});

it('does not add loading chrome to modal actions', function (): void {
    $html = renderDetailActions([
        ['label' => 'Open Modal', 'modalComponent' => 'zz::modal', 'loading' => 'Sending...'],
    ], 5);

    expect($html)->toContain('Open Modal')
        ->not->toContain('Sending...')
        ->not->toContain('wire:loading');
});

it('renders a url action as a new-tab link resolved from the urls map', function (): void {
    $html = renderDetailActions([
        ['label' => 'Open Table', 'url' => 'tableUrl', 'heroicon' => 'arrow-top-right-on-square'],
    ], 5, ['tableUrl' => 'https://example.test/t/abc']);

    expect($html)->toContain('href="https://example.test/t/abc"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener"')
        ->toContain('Open Table')
        ->not->toContain('wire:click');
});

it('renders a literal url action without the urls map', function (): void {
    $html = renderDetailActions([
        ['label' => 'Open Docs', 'url' => '/docs'],
    ], 5);

    expect($html)->toContain('href="/docs"')
        ->toContain('Open Docs');
});

it('keeps a url action in the same tab when newTab is false', function (): void {
    $html = renderDetailActions([
        ['label' => 'Same Tab', 'url' => '/docs', 'newTab' => false],
    ], 5);

    expect($html)->toContain('href="/docs"')
        ->not->toContain('target="_blank"');
});

it('hides a url action whose key is missing from the urls map', function (): void {
    $html = renderDetailActions([
        ['label' => 'Ghost Link', 'url' => 'missingUrl'],
    ], 5, ['tableUrl' => 'https://example.test/t/abc']);

    expect($html)->not->toContain('Ghost Link');
});

it('prefers a modal target over a url on the same action', function (): void {
    $html = renderDetailActions([
        ['label' => 'Open Modal', 'modalComponent' => 'zz::modal', 'url' => '/docs'],
    ], 5);

    expect($html)->toContain('$modal(')
        ->not->toContain('href="/docs"');
});

it('binds a showIf action to an Alpine x-show on the component property', function (): void {
    $html = renderDetailActions([
        ['label' => 'Login As Customer', 'action' => 'loginAsCustomer', 'showIf' => 'hasAccount'],
    ], 5);

    expect($html)->toContain('x-show="$wire.hasAccount"')
        ->toContain('wire:click="loginAsCustomer"');
});

it('negates a showIfNot action and combines both conditions on one action', function (): void {
    $html = renderDetailActions([
        ['label' => 'Invite', 'action' => 'invite', 'showIf' => 'hasEmail', 'showIfNot' => 'hasAccount'],
    ], 5);

    expect(html_entity_decode($html))->toContain('x-show="$wire.hasEmail && !$wire.hasAccount"');
});

it('compares against a value with the object condition form', function (): void {
    $html = renderDetailActions([
        [
            'label' => 'Archive',
            'action' => 'archive',
            'showIf' => ['field' => 'detailData.status', 'value' => 'open'],
        ],
    ], 5);

    expect(html_entity_decode($html))->toContain("x-show=\"(\$wire.detailData.status === 'open')\"");
});

it('leaves an unconditional action free of an x-show directive', function (): void {
    $html = renderDetailActions([
        ['label' => 'Always', 'action' => 'always'],
    ], 5);

    expect($html)->toContain('Always')
        ->not->toContain('x-show');
});

it('hides the whole action bar when every action is conditional', function (): void {
    $html = renderDetailActions([
        ['label' => 'A', 'action' => 'a', 'showIf' => 'flagA'],
        ['label' => 'B', 'action' => 'b', 'showIf' => 'flagB'],
    ], 5);

    expect(html_entity_decode($html))->toContain('x-show="($wire.flagA) || ($wire.flagB)"');
});

it('keeps the action bar visible when at least one action is unconditional', function (): void {
    $html = renderDetailActions([
        ['label' => 'A', 'action' => 'a', 'showIf' => 'flagA'],
        ['label' => 'B', 'action' => 'b'],
    ], 5);

    expect(html_entity_decode($html))->not->toContain('x-show="($wire.flagA)');
});

it('keeps a conditional modal action clickable with a single Alpine scope', function (): void {
    $html = renderDetailActions([
        [
            'label' => 'New Order',
            'modalComponent' => 'pos::pos-order-modal',
            'showIf' => 'hasAccount',
        ],
        ['label' => 'Always', 'action' => 'always'],
    ], 5);

    expect($html)->toContain('$modal(')
        ->toContain('x-show="$wire.hasAccount"')
        ->and(mb_substr_count($html, 'x-data'))->toBe(1);
});
