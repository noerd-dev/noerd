<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

uses(Tests\TestCase::class);

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
