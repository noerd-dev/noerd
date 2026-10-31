<?php

declare(strict_types=1);

use Carbon\Carbon;
use Noerd\Services\PicklistRegistry;
use Noerd\Support\ListFilter;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The header-filter value object: how a filter is built from the YAML and the
 | array form, how its options normalise, and how a date value resolves.
 */

it('resolves the relative date filter keys from the start of their period', function (): void {
    Carbon::setTestNow('2026-09-02'); // a Wednesday

    expect(ListFilter::resolveDate('today'))->toBe('2026-09-02')
        ->and(ListFilter::resolveDate('this_week'))->toBe('2026-08-31')
        ->and(ListFilter::resolveDate('this_month'))->toBe('2026-09-01')
        ->and(ListFilter::resolveDate('last_month'))->toBe('2026-08-01')
        ->and(ListFilter::resolveDate('this_quarter'))->toBe('2026-07-01')
        ->and(ListFilter::resolveDate('last_quarter'))->toBe('2026-04-01')
        ->and(ListFilter::resolveDate('this_year'))->toBe('2026-01-01')
        ->and(ListFilter::resolveDate('2026-03-31'))->toBe('2026-03-31')
        ->and(ListFilter::resolveDate('not a date'))->toBeNull();

    Carbon::setTestNow();
});

it('normalises both option shapes and drops the empty "all" entry', function (): void {
    $map = ListFilter::options('Status', 'status', ['' => 'All', 'open' => 'Open', 'done' => 'Done']);
    $entries = ListFilter::options('Status', 'status', [
        ['value' => 'open', 'label' => 'Open'],
        ['value' => 'done', 'label' => 'Done'],
    ]);

    expect($map->resolvedOptions())->toBe(['open' => 'Open', 'done' => 'Done'])
        ->and($entries->resolvedOptions())->toBe(['open' => 'Open', 'done' => 'Done'])
        ->and($map->toArray())->toBe([
            'label' => 'Status',
            'column' => 'status',
            'type' => 'Picklist',
            'options' => ['open' => 'Open', 'done' => 'Done'],
        ]);
});

it('builds a filter from the array form the get*ListFilter methods used to return', function (): void {
    $picklist = ListFilter::fromArray(['label' => 'Color', 'column' => 'color', 'options' => ['red' => 'Red']]);
    $from = ListFilter::fromArray(['label' => 'From', 'column' => 'show_from', 'type' => 'ShowFrom']);
    $until = ListFilter::fromArray(['label' => 'Until', 'column' => 'show_until', 'type' => 'ShowUntil']);

    expect($picklist->kind)->toBe(ListFilter::KIND_PICKLIST)
        ->and($picklist->toArray()['type'])->toBe('Picklist')
        ->and($from->kind)->toBe(ListFilter::KIND_DATE_FROM)
        ->and($from->toArray()['type'])->toBe('ShowFrom')
        ->and(array_keys($from->toArray()['options']))->toBe(ListFilter::DATE_PRESETS)
        ->and($until->toArray()['type'])->toBe('ShowUntil');
});

it('builds a filter from a list YAML entry with translated labels and a default', function (): void {
    app('translator')->addLines(['*.Zz Open' => 'Zz Offen', '*.Zz Status' => 'Zz Zustand'], 'de');
    app()->setLocale('de');

    $filter = ListFilter::fromArray([
        'label' => 'Zz Status',
        'column' => 'status',
        'default' => 'open',
        'options' => [
            ['value' => 'open', 'label' => 'Zz Open'],
            ['value' => 'done', 'label' => 'Done'],
        ],
    ], translate: true);

    expect($filter->label)->toBe('Zz Zustand')
        ->and($filter->defaultValue())->toBe('open')
        ->and($filter->resolvedOptions())->toBe(['open' => 'Zz Offen', 'done' => 'Done']);
});

it('takes the options of a YAML filter from a picklist provider', function (): void {
    app(PicklistRegistry::class)->register('zzFilterStates', fn(): array => [
        ['value' => 'a', 'label' => 'Alpha'],
        ['value' => 'b', 'label' => 'Beta'],
    ]);

    $filter = ListFilter::fromArray(['label' => 'State', 'column' => 'state', 'optionsMethod' => 'zzFilterStates']);
    $unknown = ListFilter::fromArray(['label' => 'State', 'column' => 'state', 'optionsMethod' => 'zzNoSuchProvider']);

    expect($filter->resolvedOptions())->toBe(['a' => 'Alpha', 'b' => 'Beta'])
        ->and($unknown->resolvedOptions())->toBe([]);
});
