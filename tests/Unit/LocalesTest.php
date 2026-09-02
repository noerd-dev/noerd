<?php

declare(strict_types=1);

use Noerd\Support\Locales;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('formats a number and a date for every supported locale', function (string $locale): void {
    $sample = Locales::sample($locale);

    expect($sample)->toContain('·')
        ->and(preg_match('/\d/', $sample))->toBe(1)
        ->and(Locales::label($locale, 'en'))->toStartWith(Locale::getDisplayName($locale, 'en'));
})->with(Locales::SUPPORTED);

it('knows which locales it supports', function (): void {
    expect(Locales::isSupported('de-DE'))->toBeTrue()
        ->and(Locales::isSupported('de_de'))->toBeTrue()
        ->and(Locales::isSupported('de'))->toBeFalse()
        ->and(Locales::isSupported('xx-XX'))->toBeFalse()
        ->and(Locales::isSupported(null))->toBeFalse();
});

it('normalizes casing and separators', function (): void {
    expect(Locales::normalize('en_us'))->toBe('en-US')
        ->and(Locales::normalize('EN-gb'))->toBe('en-GB')
        ->and(Locales::normalize('de'))->toBe('de');
});

it('derives the default locale of a language', function (): void {
    expect(Locales::defaultFor('de'))->toBe('de-DE')
        ->and(Locales::defaultFor('en'))->toBe('en-US')
        ->and(Locales::defaultFor('fr'))->toBe('fr-FR')
        ->and(Locales::defaultFor('de-CH'))->toBe('de-CH')
        ->and(Locales::defaultFor('de-XX'))->toBe('de-DE')
        ->and(Locales::defaultFor('xx'))->toBe(Locales::DEFAULT)
        ->and(Locales::defaultFor(null))->toBe(Locales::DEFAULT);
});

it('labels the picker options in the interface language', function (): void {
    $options = Locales::options('de');

    expect(array_keys($options))->toBe(Locales::SUPPORTED)
        ->and($options['de-DE'])->toStartWith('Deutsch (Deutschland) · ')
        ->and($options['en-US'])->toContain('1,234.56');
});
