<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Tests\TestCase;

uses(TestCase::class);

if (! function_exists('renderDetailActions')) {
    function renderDetailActions(array $actions, mixed $modelId = null): string
    {
        return Blade::render(
            '<x-noerd::detail-actions :layout="$layout" :modelId="$modelId" />',
            ['layout' => ['actions' => $actions], 'modelId' => $modelId],
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
