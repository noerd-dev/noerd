<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Noerd\Helpers\FormatHelper;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('formats a numeric value as a whole number', function (): void {
    $html = Blade::render('<x-noerd::dashboard-card title="Orders" :value="$v" />', ['v' => 1234.6]);

    expect($html)->toContain(FormatHelper::decimal(1234.6, 0));
});

it('formats a numeric string like a number', function (): void {
    $html = Blade::render('<x-noerd::dashboard-card title="Orders" :value="$v" />', ['v' => '12.00']);

    expect($html)->toContain('>' . FormatHelper::decimal(12.0, 0) . '<')
        ->and($html)->not->toContain('12.00');
});

it('renders an already formatted value as is', function (): void {
    $html = Blade::render('<x-noerd::dashboard-card title="Balance" :value="$v" />', ['v' => '€ 38,50']);

    expect($html)->toContain('€ 38,50');
});

it('escapes a string value', function (): void {
    $html = Blade::render('<x-noerd::dashboard-card title="Balance" :value="$v" />', ['v' => '<b>x</b>']);

    expect($html)->not->toContain('<b>x</b>')
        ->and($html)->toContain('&lt;b&gt;x&lt;/b&gt;');
});
