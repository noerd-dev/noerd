<?php

use Noerd\Helpers\CurrencyHelper;

uses(Tests\TestCase::class);

it('formats currency with default config (German/Euro)', function (): void {
    config()->set('noerd.currency', [
        'symbol' => '€',
        'decimal_separator' => ',',
        'thousands_separator' => '.',
        'symbol_position' => 'after',
    ]);

    expect(CurrencyHelper::format(1234.56))->toBe('1.234,56 €');
});

it('formats currency with US format', function (): void {
    config()->set('noerd.currency', [
        'symbol' => '$',
        'decimal_separator' => '.',
        'thousands_separator' => ',',
        'symbol_position' => 'before',
    ]);

    expect(CurrencyHelper::format(1234.56))->toBe('$ 1,234.56');
});

it('always shows two decimal places', function (): void {
    config()->set('noerd.currency', [
        'symbol' => '€',
        'decimal_separator' => ',',
        'thousands_separator' => '.',
        'symbol_position' => 'after',
    ]);

    expect(CurrencyHelper::format(100.0))->toBe('100,00 €')
        ->and(CurrencyHelper::format(99.9))->toBe('99,90 €')
        ->and(CurrencyHelper::format(0.0))->toBe('0,00 €');
});

it('handles large numbers with thousands separators', function (): void {
    config()->set('noerd.currency', [
        'symbol' => '€',
        'decimal_separator' => ',',
        'thousands_separator' => '.',
        'symbol_position' => 'after',
    ]);

    expect(CurrencyHelper::format(1000000.50))->toBe('1.000.000,50 €');
});

it('handles negative values', function (): void {
    config()->set('noerd.currency', [
        'symbol' => '€',
        'decimal_separator' => ',',
        'thousands_separator' => '.',
        'symbol_position' => 'after',
    ]);

    expect(CurrencyHelper::format(-42.10))->toBe('-42,10 €');
});

it('uses fallback defaults when config is empty', function (): void {
    config()->set('noerd.currency', []);

    expect(CurrencyHelper::format(10.5))->toBe('10,50 €');
});
